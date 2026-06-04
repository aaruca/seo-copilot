<?php

namespace SeoCopilot\Runs;

use SeoCopilot\Database\Schema;
use SeoCopilot\Providers\OpenAIBatchClient;
use SeoCopilot\Providers\PromptAssembler;
use SeoCopilot\Support\Logger;
use SeoCopilot\Templates\TemplateRepository;

/**
 * Orchestrates the OpenAI Batch API lifecycle for bulk jobs queued with
 * dispatch='batch'. Phases:
 *
 *   A. Submit drafts  — build a JSONL from queue rows, upload, create batch.
 *   B. Poll active    — GET status; on completion, fetch output JSONL,
 *                       write each response back to its queue row.
 *   C. Apply ready    — apply the stored response to the post (mode='apply')
 *                       or store a proposal (mode='review').
 *
 * Driven by the existing seocp_run_bulk_batch cron event (every minute) plus
 * the browser-side /bulk/tick poll.
 */
class BatchDispatcher
{
    /** Max chunk size per OpenAI batch. The API allows up to 50,000, but
     *  smaller chunks keep JSONL builds inside one cron tick and reduce the
     *  blast radius of a single failed upload. Filterable via
     *  `seocp_openai_batch_chunk_size`. */
    public const DEFAULT_CHUNK_SIZE = 5000;

    /** OpenAI Batch pricing is 50% off the synchronous list price. */
    private const BATCH_PRICE_MULTIPLIER = 0.5;

    /** How many OpenAI batches we allow in flight at once. OpenAI enforces a
     *  per-model *enqueued-token* limit across all your in-progress batches;
     *  firing every chunk at once blows past it and the surplus batches fail
     *  wholesale. Serializing (1 at a time) keeps the enqueued total to a
     *  single chunk's worth, which is the safe default. Raise it via
     *  `seocp_openai_batch_concurrency` only if your org's batch limit is high. */
    public const DEFAULT_CONCURRENCY = 1;

    /** Max times a chunk is re-submitted after a *transient* failure (token /
     *  rate limit, or expiry) before we give up and fail its rows for good.
     *  Filterable via `seocp_openai_batch_max_retries`. */
    public const DEFAULT_MAX_RETRIES = 5;

    private OpenAIBatchClient $client;
    private PromptAssembler $assembler;
    private TemplateRepository $templates;
    private Runner $runner;
    private SegmentRepository $segments;
    private OpenAIBatchRepository $batches;
    private Logger $logger;

    public function __construct(
        OpenAIBatchClient $client,
        PromptAssembler $assembler,
        TemplateRepository $templates,
        Runner $runner,
        SegmentRepository $segments,
        OpenAIBatchRepository $batches,
        Logger $logger
    ) {
        $this->client    = $client;
        $this->assembler = $assembler;
        $this->templates = $templates;
        $this->runner    = $runner;
        $this->segments  = $segments;
        $this->batches   = $batches;
        $this->logger    = $logger;
    }

    public static function chunk_size(): int
    {
        $size = (int) apply_filters('seocp_openai_batch_chunk_size', self::DEFAULT_CHUNK_SIZE);
        return max(1, min(50000, $size));
    }

    public static function concurrency(): int
    {
        $n = (int) apply_filters('seocp_openai_batch_concurrency', self::DEFAULT_CONCURRENCY);
        return max(1, $n);
    }

    public static function max_retries(): int
    {
        $n = (int) apply_filters('seocp_openai_batch_max_retries', self::DEFAULT_MAX_RETRIES);
        return max(0, $n);
    }

    /**
     * Called from BulkRunner::enqueue*() after the queue rows have been
     * inserted. Plans one or more OpenAI-batch chunks covering all queue rows
     * with the given $batch_id whose dispatch='batch'. Returns the number of
     * chunks planned.
     */
    public function plan_chunks_for_batch(string $batch_id): int
    {
        global $wpdb;
        $queue = Schema::table('queue');
        $settings = get_option('seocp_settings', []);
        $model = (string) ($settings['openai_model'] ?? 'gpt-4.1-mini');
        $size = self::chunk_size();

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$queue}
              WHERE batch_id = %s AND dispatch = 'batch' AND status = 'pending'
              ORDER BY id ASC",
            $batch_id
        ), ARRAY_A) ?: [];
        if (!$rows) {
            return 0;
        }

        $planned = 0;
        $buffer  = [];
        $chunk_index = 0;
        foreach ($rows as $r) {
            $buffer[] = (int) $r['id'];
            if (count($buffer) >= $size) {
                $this->batches->plan($batch_id, $chunk_index++, $buffer[0], $buffer[count($buffer) - 1], count($buffer), $model);
                $planned++;
                $buffer = [];
            }
        }
        if ($buffer) {
            $this->batches->plan($batch_id, $chunk_index, $buffer[0], $buffer[count($buffer) - 1], count($buffer), $model);
            $planned++;
        }
        return $planned;
    }

    /**
     * Cron entry-point — runs every minute. Each call advances at most one
     * draft (Phase A), one active batch through Phase B, and a fixed slice of
     * Phase C apply work.
     */
    public function tick(): void
    {
        $this->raise_limits();

        // Phase A: submit one draft per tick.
        try {
            $this->submit_next_draft();
        } catch (\Throwable $e) {
            $this->logger->error('Batch submit failed', ['msg' => $e->getMessage()]);
        }

        // Phase B: poll all active batches.
        try {
            $this->poll_active();
        } catch (\Throwable $e) {
            $this->logger->error('Batch poll failed', ['msg' => $e->getMessage()]);
        }

        // Phase C: apply ready queue rows (handled by BulkRunner::drain_ready
        // which is called from the same cron event — we don't duplicate here).
    }

    /**
     * Phase A — pick the oldest draft openai_batches row, build a JSONL of
     * its queue rows, upload to OpenAI, create the batch, and stamp the row.
     */
    public function submit_next_draft(): bool
    {
        // Concurrency gate. OpenAI caps the *enqueued tokens* across all your
        // in-progress batches per model; submitting every chunk at once exceeds
        // it and the surplus batches fail wholesale. Only submit a new chunk
        // when we're below the concurrency budget — the rest wait as drafts and
        // get picked up as earlier chunks finish.
        if ($this->batches->count_in_flight() >= self::concurrency()) {
            return false;
        }

        $draft = $this->batches->next_draft();
        if (!$draft) {
            return false;
        }
        $row_id = (int) $draft['id'];
        $this->batches->update($row_id, ['status' => 'building']);

        try {
            $tmpdir = $this->batch_dir();
            $path = $tmpdir . '/input-' . (string) $draft['batch_id'] . '-' . (int) $draft['chunk_index'] . '.jsonl';
            $count = $this->build_jsonl($path, (string) $draft['batch_id'], (int) $draft['queue_id_min'], (int) $draft['queue_id_max']);
            if ($count === 0) {
                $this->batches->update($row_id, [
                    'status'        => 'completed',
                    'completed_at'  => current_time('mysql'),
                    'error_message' => 'No queue rows in range — nothing to submit.',
                ]);
                @unlink($path);
                return true;
            }

            $file_id  = $this->client->upload_file($path);
            $batch_in = $this->client->create_batch($file_id, [
                'seocp_batch_id'    => (string) $draft['batch_id'],
                'seocp_chunk_index' => (string) $draft['chunk_index'],
            ]);

            $this->batches->update($row_id, [
                'status'          => $this->normalize_status($batch_in['status']),
                'openai_batch_id' => $batch_in['id'],
                'input_file_id'   => $file_id,
                'submitted_at'    => current_time('mysql'),
                'request_count'   => $count,
                'last_polled_at'  => current_time('mysql'),
            ]);
            // Mark queue rows as submitted so the wizard surfaces progress.
            $this->mark_queue_submitted((string) $draft['batch_id'], (int) $draft['queue_id_min'], (int) $draft['queue_id_max']);

            @unlink($path);
            return true;
        } catch (\Throwable $e) {
            // Queue rows in this range are still 'pending' (mark_queue_submitted
            // runs only on success), so a retry just resets the chunk to 'draft'.
            $this->handle_chunk_failure(
                $row_id,
                (string) $draft['batch_id'],
                (int) $draft['queue_id_min'],
                (int) $draft['queue_id_max'],
                (int) $draft['attempts'],
                $e->getMessage(),
                'validating', // a thrown create/upload error has no remote status
                false         // queue rows are still pending — nothing to reset
            );
            return false;
        }
    }

    /**
     * Phase B — poll every active batch. On completion, download the output
     * JSONL and stamp each queue row with its response payload so the apply
     * phase can pick them up.
     */
    public function poll_active(): int
    {
        $active = $this->batches->active();
        $polled = 0;
        foreach ($active as $row) {
            $row_id = (int) $row['id'];
            $oai_id = (string) ($row['openai_batch_id'] ?? '');
            if ($oai_id === '') {
                continue;
            }
            try {
                $remote = $this->client->get_batch($oai_id);
                $status = $this->normalize_status((string) ($remote['status'] ?? 'in_progress'));
                $counts = (array) ($remote['request_counts'] ?? []);
                $update = [
                    'status'          => $status,
                    'last_polled_at'  => current_time('mysql'),
                    'completed_count' => (int) ($counts['completed'] ?? 0),
                    'failed_count'    => (int) ($counts['failed']    ?? 0),
                ];
                if (!empty($remote['output_file_id'])) {
                    $update['output_file_id'] = (string) $remote['output_file_id'];
                }
                if (!empty($remote['error_file_id'])) {
                    $update['error_file_id'] = (string) $remote['error_file_id'];
                }
                if ($status === 'completed' && !empty($remote['output_file_id'])) {
                    $update['completed_at'] = current_time('mysql');
                    $this->batches->update($row_id, $update);
                    $this->ingest_output((string) $row['batch_id'], (string) $remote['output_file_id'], (int) $row['queue_id_min'], (int) $row['queue_id_max']);
                    // Individually-errored requests land in a separate error file.
                    if (!empty($remote['error_file_id'])) {
                        $this->ingest_errors((string) $row['batch_id'], (string) $remote['error_file_id']);
                    }
                    // Any row still 'submitted' wasn't in either file — resolve it
                    // so the batch can't hang at < 100% forever.
                    $this->fail_unresolved((string) $row['batch_id'], (int) $row['queue_id_min'], (int) $row['queue_id_max'], 'No response returned for this item by the OpenAI batch.');
                } elseif (in_array($status, ['failed', 'expired'], true)) {
                    // Transient failures (token / rate limit, expiry) are retried
                    // so a momentary capacity crunch doesn't permanently kill a
                    // whole chunk. `cancelled` is a deliberate user action — see
                    // the next branch — and is never retried.
                    $msg = (string) ($remote['errors']['data'][0]['message'] ?? $status);
                    $this->batches->update($row_id, [
                        'last_polled_at'  => current_time('mysql'),
                        'completed_count' => (int) ($counts['completed'] ?? 0),
                        'failed_count'    => (int) ($counts['failed'] ?? 0),
                    ]);
                    $this->handle_chunk_failure(
                        $row_id,
                        (string) $row['batch_id'],
                        (int) $row['queue_id_min'],
                        (int) $row['queue_id_max'],
                        (int) ($row['attempts'] ?? 0),
                        $msg,
                        $status,
                        true // these rows were marked 'submitted' — reset them to pending on retry
                    );
                } elseif ($status === 'cancelled') {
                    $update['completed_at'] = current_time('mysql');
                    $update['error_message'] = (string) ($remote['errors']['data'][0]['message'] ?? $status);
                    $this->batches->update($row_id, $update);
                    $this->fail_queue_range((string) $row['batch_id'], (int) $row['queue_id_min'], (int) $row['queue_id_max'], $update['error_message']);
                } else {
                    $this->batches->update($row_id, $update);
                }
                $polled++;
            } catch (\Throwable $e) {
                $this->logger->error('Batch poll error', ['id' => $row_id, 'msg' => $e->getMessage()]);
                $this->batches->update($row_id, ['last_polled_at' => current_time('mysql')]);
            }
        }
        return $polled;
    }

    /**
     * Phase C — drain `dispatch='batch' AND status='ready'` queue rows: parse
     * each row's stored payload_response, apply (or store proposal). Returns
     * the count of rows processed.
     */
    public function drain_ready(?string $batch_id, int $limit): int
    {
        global $wpdb;
        $queue = Schema::table('queue');
        $limit = max(1, $limit);
        if ($batch_id !== null) {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$queue}
                  WHERE dispatch = 'batch' AND status = 'ready' AND batch_id = %s
                  ORDER BY id ASC LIMIT %d",
                $batch_id, $limit
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$queue}
                  WHERE dispatch = 'batch' AND status = 'ready'
                  ORDER BY id ASC LIMIT %d",
                $limit
            );
        }
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        foreach ($rows as $row) {
            $this->apply_row($row);
        }
        return count($rows);
    }

    /**
     * Cancel every active OpenAI batch tied to an internal batch_id, plus
     * fail any pending/ready/submitted queue rows. Returns counts.
     *
     * @return array{cancelled_chunks:int,cancelled_rows:int}
     */
    public function cancel(string $batch_id): array
    {
        global $wpdb;
        $queue = Schema::table('queue');

        // Cancel remote batches.
        $active = $this->batches->for_batch($batch_id);
        foreach ($active as $row) {
            if (in_array((string) $row['status'], ['submitted', 'validating', 'in_progress', 'finalizing'], true)) {
                try {
                    $this->client->cancel_batch((string) $row['openai_batch_id']);
                } catch (\Throwable $e) {
                    $this->logger->error('Batch cancel API failed', ['msg' => $e->getMessage()]);
                }
            }
        }
        $cancelled_chunks = $this->batches->cancel_for_batch($batch_id);

        // Cancel queue rows that haven't applied yet. Already-applied rows
        // keep their writes — we never roll back.
        $cancelled_rows = (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$queue}
                SET status = 'cancelled', completed_at = %s
              WHERE batch_id = %s AND dispatch = 'batch' AND status IN ('pending','submitted','ready')",
            current_time('mysql'), $batch_id
        ));
        return ['cancelled_chunks' => $cancelled_chunks, 'cancelled_rows' => $cancelled_rows];
    }

    /* ===================== internal ===================== */

    /**
     * Stream a JSONL file: one line per queue row, each a fully-formed
     * /v1/chat/completions request with custom_id = "q-<queue_id>". Returns
     * the line count actually written.
     */
    private function build_jsonl(string $path, string $batch_id, int $queue_id_min, int $queue_id_max): int
    {
        global $wpdb;
        $queue = Schema::table('queue');
        $settings = get_option('seocp_settings', []);
        $model = (string) ($settings['openai_model'] ?? 'gpt-4.1-mini');

        $dir = dirname($path);
        if (!is_dir($dir) && !wp_mkdir_p($dir)) {
            throw new \RuntimeException('Could not create batch tmp dir: ' . $dir);
        }
        $fh = fopen($path, 'wb');
        if (!$fh) {
            throw new \RuntimeException('Could not open batch tmp file: ' . $path);
        }

        $page_size = 200;
        $cursor = $queue_id_min - 1;
        $written = 0;
        while (true) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, post_id, template_id, fields_picked
                   FROM {$queue}
                  WHERE batch_id = %s AND dispatch = 'batch' AND id > %d AND id <= %d
                  ORDER BY id ASC LIMIT %d",
                $batch_id, $cursor, $queue_id_max, $page_size
            ), ARRAY_A) ?: [];
            if (!$rows) break;
            foreach ($rows as $row) {
                $line = $this->build_jsonl_line((int) $row['id'], (int) $row['post_id'], (int) $row['template_id'], (string) $row['fields_picked'], $model);
                if ($line === null) {
                    // Couldn't build prompt — mark row failed, skip.
                    $wpdb->update($queue, [
                        'status'        => 'failed',
                        'error_message' => 'Could not assemble prompt (deleted post or template).',
                        'completed_at'  => current_time('mysql'),
                    ], ['id' => (int) $row['id']]);
                    continue;
                }
                fwrite($fh, $line . "\n");
                $written++;
                $cursor = (int) $row['id'];
            }
            if (count($rows) < $page_size) break;
        }
        fclose($fh);
        return $written;
    }

    private function build_jsonl_line(int $queue_id, int $post_id, int $template_id, string $fields_picked_json, string $model): ?string
    {
        $fields = json_decode($fields_picked_json, true) ?: [];
        $tpl = $this->templates->find($template_id);
        if (!$tpl || !get_post($post_id)) {
            return null;
        }
        $prompt = $this->assembler->assemble($tpl, $post_id, array_map('strval', $fields));
        $line = [
            'custom_id' => 'q-' . $queue_id,
            'method'    => 'POST',
            'url'       => '/v1/chat/completions',
            'body'      => [
                'model'           => $model,
                'response_format' => ['type' => 'json_object'],
                'messages'        => [
                    ['role' => 'system', 'content' => $prompt['system']],
                    ['role' => 'user',   'content' => $prompt['user']],
                ],
                'temperature'     => 0.6,
            ],
        ];
        return (string) wp_json_encode($line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function mark_queue_submitted(string $batch_id, int $queue_id_min, int $queue_id_max): void
    {
        global $wpdb;
        $queue = Schema::table('queue');
        $wpdb->query($wpdb->prepare(
            "UPDATE {$queue}
                SET status = 'submitted',
                    openai_custom_id = CONCAT('q-', id),
                    started_at = %s
              WHERE batch_id = %s AND dispatch = 'batch' AND status = 'pending' AND id BETWEEN %d AND %d",
            current_time('mysql'), $batch_id, $queue_id_min, $queue_id_max
        ));
    }

    private function fail_queue_range(string $batch_id, int $queue_id_min, int $queue_id_max, string $msg): void
    {
        global $wpdb;
        $queue = Schema::table('queue');
        $wpdb->query($wpdb->prepare(
            "UPDATE {$queue}
                SET status = 'failed', error_message = %s, completed_at = %s
              WHERE batch_id = %s AND dispatch = 'batch'
                AND id BETWEEN %d AND %d
                AND status IN ('pending','submitted','ready')",
            $msg, current_time('mysql'), $batch_id, $queue_id_min, $queue_id_max
        ));
    }

    /**
     * Roll a chunk's queue rows back to 'pending' so a re-submitted chunk
     * re-enqueues them. Only touches rows that were 'submitted' (sent to the
     * failed batch) — never rows already 'ready'/'completed'.
     */
    private function reset_queue_to_pending(string $batch_id, int $queue_id_min, int $queue_id_max): void
    {
        global $wpdb;
        $queue = Schema::table('queue');
        $wpdb->query($wpdb->prepare(
            "UPDATE {$queue}
                SET status = 'pending', started_at = NULL, openai_custom_id = NULL
              WHERE batch_id = %s AND dispatch = 'batch'
                AND id BETWEEN %d AND %d
                AND status = 'submitted'",
            $batch_id, $queue_id_min, $queue_id_max
        ));
    }

    /**
     * Decide whether a failed chunk should be retried or failed for good.
     *
     * Retryable causes (token/rate-limit, expiry) reset the chunk to 'draft' so
     * the concurrency-gated submitter picks it up again once capacity frees.
     * Anything else — or a chunk that's out of retries — is failed permanently
     * and its queue rows are marked failed.
     *
     * @param bool $rows_submitted Whether the queue rows were already moved to
     *                             'submitted' (poll path) vs still 'pending'
     *                             (submit-catch path).
     */
    private function handle_chunk_failure(
        int $row_id,
        string $batch_id,
        int $queue_id_min,
        int $queue_id_max,
        int $attempts,
        string $msg,
        string $status,
        bool $rows_submitted
    ): void {
        $retryable = $this->is_retryable_failure($msg, $status);
        if ($retryable && $attempts < self::max_retries()) {
            // Re-queue the chunk. Reset to draft, bump attempts, clear the stale
            // OpenAI handles so it builds a fresh input file next time.
            if ($rows_submitted) {
                $this->reset_queue_to_pending($batch_id, $queue_id_min, $queue_id_max);
            }
            $this->batches->update($row_id, [
                'status'          => 'draft',
                'attempts'        => $attempts + 1,
                'openai_batch_id' => null,
                'input_file_id'   => null,
                'error_message'   => sprintf('Retry %d/%d after: %s', $attempts + 1, self::max_retries(), $msg),
            ]);
            $this->logger->info('Batch chunk re-queued after transient failure', [
                'id' => $row_id, 'attempt' => $attempts + 1, 'msg' => $msg,
            ]);
            return;
        }

        // Permanent failure.
        $this->batches->update($row_id, [
            'status'        => 'failed',
            'error_message' => $msg,
            'completed_at'  => current_time('mysql'),
        ]);
        $this->fail_queue_range($batch_id, $queue_id_min, $queue_id_max, $msg);
        $this->logger->error('Batch chunk failed permanently', [
            'id' => $row_id, 'attempts' => $attempts, 'msg' => $msg,
        ]);
    }

    /**
     * Heuristic: is this batch failure worth retrying? Token/rate-limit
     * pressure and expiry are transient; validation errors in our own JSONL
     * are not (retrying would just fail the same way).
     */
    private function is_retryable_failure(string $msg, string $status): bool
    {
        if ($status === 'expired') {
            return true;
        }
        $needles = ['enqueued', 'token limit', 'rate limit', 'rate_limit', 'too many requests', 'try again', 'temporarily', 'capacity', '429', '503'];
        $hay = strtolower($msg);
        foreach ($needles as $n) {
            if (strpos($hay, $n) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Stream the output JSONL, write each response back to its queue row,
     * and flip the row to 'ready' for Phase C.
     */
    private function ingest_output(string $batch_id, string $output_file_id, int $queue_id_min, int $queue_id_max): void
    {
        global $wpdb;
        $queue = Schema::table('queue');
        $raw = $this->client->get_file_content($output_file_id);
        if ($raw === '') {
            return;
        }
        $lines = preg_split("/\r?\n/", $raw) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $decoded = json_decode($line, true);
            if (!is_array($decoded) || empty($decoded['custom_id'])) continue;
            $custom_id = (string) $decoded['custom_id'];
            if (strncmp($custom_id, 'q-', 2) !== 0) continue;
            $queue_id = (int) substr($custom_id, 2);
            if ($queue_id <= 0) continue;

            $resp = (array) ($decoded['response'] ?? []);
            $err  = $decoded['error'] ?? null;
            $code = (int) ($resp['status_code'] ?? 0);
            if ($err || $code !== 200) {
                $msg = is_array($err) ? (string) ($err['message'] ?? 'batch error') : ('HTTP ' . $code);
                $wpdb->update($queue, [
                    'status'        => 'failed',
                    'error_message' => $msg,
                    'completed_at'  => current_time('mysql'),
                ], ['id' => $queue_id]);
                continue;
            }
            $wpdb->update($queue, [
                'status'           => 'ready',
                'payload_response' => wp_json_encode($resp['body'] ?? []),
            ], ['id' => $queue_id]);
        }
    }

    /**
     * Parse a batch error file (JSONL) and mark each referenced queue row
     * failed with its error message.
     */
    private function ingest_errors(string $batch_id, string $error_file_id): void
    {
        global $wpdb;
        $queue = Schema::table('queue');
        try {
            $raw = $this->client->get_file_content($error_file_id);
        } catch (\Throwable $e) {
            $this->logger->error('Batch error-file fetch failed', ['msg' => $e->getMessage()]);
            return;
        }
        if ($raw === '') {
            return;
        }
        $lines = preg_split("/\r?\n/", $raw) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $decoded = json_decode($line, true);
            if (!is_array($decoded) || empty($decoded['custom_id'])) continue;
            $custom_id = (string) $decoded['custom_id'];
            if (strncmp($custom_id, 'q-', 2) !== 0) continue;
            $queue_id = (int) substr($custom_id, 2);
            if ($queue_id <= 0) continue;
            $err = $decoded['error'] ?? $decoded['response']['body']['error'] ?? null;
            $msg = is_array($err) ? (string) ($err['message'] ?? 'batch error') : 'batch error';
            $wpdb->update($queue, [
                'status'        => 'failed',
                'error_message' => $msg,
                'completed_at'  => current_time('mysql'),
            ], ['id' => $queue_id]);
        }
    }

    /**
     * Fail any rows in a completed chunk's range that are still 'submitted'
     * (i.e. appeared in neither the output nor the error file).
     */
    private function fail_unresolved(string $batch_id, int $queue_id_min, int $queue_id_max, string $msg): void
    {
        global $wpdb;
        $queue = Schema::table('queue');
        $wpdb->query($wpdb->prepare(
            "UPDATE {$queue}
                SET status = 'failed', error_message = %s, completed_at = %s
              WHERE batch_id = %s AND dispatch = 'batch'
                AND id BETWEEN %d AND %d
                AND status = 'submitted'",
            $msg, current_time('mysql'), $batch_id, $queue_id_min, $queue_id_max
        ));
    }

    private function apply_row(array $row): void
    {
        global $wpdb;
        $queue = Schema::table('queue');
        $id = (int) $row['id'];
        $post_id = (int) $row['post_id'];
        $tpl_id  = (int) $row['template_id'];
        $fields  = json_decode((string) $row['fields_picked'], true) ?: [];
        $mode    = ((string) ($row['mode'] ?? 'apply')) === 'review' ? 'review' : 'apply';
        $batch_id = (string) $row['batch_id'];
        $created_by = (int) ($row['created_by'] ?? 0);

        // Same fix as BulkRunner::process_row — restore the originating user
        // so postmeta auth_callbacks accept writes when this runs from cron.
        $restore_user = BulkRunner::switch_user_for_batch($created_by, $post_id);

        $wpdb->update($queue, [
            'status'     => 'applying',
            'started_at' => current_time('mysql'),
            'attempts'   => (int) $row['attempts'] + 1,
        ], ['id' => $id]);

        try {
            $payload = json_decode((string) $row['payload_response'], true) ?: [];
            $content = (string) ($payload['choices'][0]['message']['content'] ?? '');
            $tokens_in  = (int) ($payload['usage']['prompt_tokens'] ?? 0);
            $tokens_out = (int) ($payload['usage']['completion_tokens'] ?? 0);
            $model      = (string) ($payload['model'] ?? '');
            if ($content === '') {
                throw new \RuntimeException('Empty batch response content.');
            }
            $cost = $this->price_response($model, $tokens_in, $tokens_out);

            $proposal = $this->runner->ingest_batch_result(
                $post_id,
                $tpl_id,
                array_map('strval', $fields),
                $content,
                $tokens_in,
                $tokens_out,
                $cost,
                $model,
                $batch_id
            );
            if ($mode === 'review') {
                foreach ($proposal as $field_id => $value) {
                    $this->segments->create($post_id, $tpl_id, $batch_id, (string) $field_id, (string) $value);
                }
            } else {
                $this->runner->apply($post_id, $tpl_id, $proposal, $batch_id);
            }
            $wpdb->update($queue, [
                'status'           => 'completed',
                'completed_at'     => current_time('mysql'),
                'payload_response' => null,
            ], ['id' => $id]);
        } catch (\Throwable $e) {
            $this->logger->error('Batch apply failed', ['id' => $id, 'msg' => $e->getMessage()]);
            $wpdb->update($queue, [
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => current_time('mysql'),
            ], ['id' => $id]);
        }
        $restore_user();
    }

    /**
     * 50%-off batch pricing. Falls back to a generic estimate when the model
     * isn't in OpenAIProvider's table.
     */
    private function price_response(string $model, int $tokens_in, int $tokens_out): float
    {
        $pricing = apply_filters('seocp_openai_pricing', [
            'gpt-4.1'        => ['in' => 0.0030, 'out' => 0.0120],
            'gpt-4.1-mini'   => ['in' => 0.0008, 'out' => 0.0032],
            'gpt-4.1-nano'   => ['in' => 0.0002, 'out' => 0.0008],
            'gpt-4o'         => ['in' => 0.0050, 'out' => 0.0150],
            'gpt-4o-mini'    => ['in' => 0.0006, 'out' => 0.0024],
        ]);
        $rate = $pricing[$model] ?? ['in' => 0.001, 'out' => 0.003];
        $sync = ($tokens_in / 1000.0) * $rate['in'] + ($tokens_out / 1000.0) * $rate['out'];
        return round($sync * self::BATCH_PRICE_MULTIPLIER, 6);
    }

    /**
     * Map OpenAI's batch status strings into the values we store. We treat
     * `submitted` as a synonym for `validating` so the row always reflects
     * the most recent server state.
     */
    private function normalize_status(string $status): string
    {
        $allowed = ['validating', 'in_progress', 'finalizing', 'completed', 'failed', 'expired', 'cancelled'];
        if (in_array($status, $allowed, true)) {
            return $status;
        }
        return 'validating';
    }

    private function batch_dir(): string
    {
        $up = wp_upload_dir();
        $dir = trailingslashit($up['basedir']) . 'seo-copilot/batches';
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
            // Keep this directory non-web-accessible if possible.
            $htaccess = $dir . '/.htaccess';
            if (!file_exists($htaccess)) {
                @file_put_contents($htaccess, "Deny from all\n");
            }
            $index = $dir . '/index.html';
            if (!file_exists($index)) {
                @file_put_contents($index, '');
            }
        }
        return $dir;
    }

    private function raise_limits(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('admin');
        }
    }
}
