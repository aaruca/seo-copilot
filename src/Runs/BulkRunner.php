<?php

namespace SeoCopilot\Runs;

use SeoCopilot\Database\Schema;
use SeoCopilot\Rest\PostsController;
use SeoCopilot\Support\Logger;

class BulkRunner
{
    /** Cap any single filter-mode enqueue at this many jobs to keep one HTTP
     *  request safe even on slow shared hosts. Filterable via `seocp_bulk_max_matching`. */
    public const FILTER_MODE_HARD_CAP = 50000;

    /** Rows per INSERT — chunked so a 60k-product enqueue stays under MySQL's
     *  max_allowed_packet and PHP's memory limit. */
    private const INSERT_CHUNK = 500;

    /** WP_Query page size when streaming IDs in filter mode. */
    private const ID_PAGE_SIZE = 2000;

    private Runner $runner;
    private Logger $logger;
    private SegmentRepository $segments;

    public function __construct(Runner $runner, Logger $logger, SegmentRepository $segments)
    {
        $this->runner   = $runner;
        $this->logger   = $logger;
        $this->segments = $segments;
    }

    private function table(): string
    {
        return Schema::table('queue');
    }

    /**
     * Enqueue an explicit list of post IDs (Smart Optimizer / small bulk runs).
     *
     * @param array<int, int>    $post_ids
     * @param array<int, string> $fields
     * @param string             $mode      'apply' (write immediately) or 'review' (store proposals for later review)
     */
    public function enqueue(array $post_ids, int $template_id, array $fields, string $mode = 'apply'): string
    {
        global $wpdb;
        $mode = self::normalize_mode($mode);
        $batch_id = wp_generate_uuid4();
        $now = current_time('mysql');
        $fields_json = wp_json_encode(array_values($fields));

        $rows = [];
        foreach ($post_ids as $pid) {
            $rows[] = $wpdb->prepare(
                '(%s, %d, %d, %s, %s, %s, %d, %s)',
                $batch_id, (int) $pid, $template_id, $fields_json, $mode, 'pending', 0, $now
            );
            if (count($rows) >= self::INSERT_CHUNK) {
                $this->insert_rows($rows);
                $rows = [];
            }
        }
        if ($rows) {
            $this->insert_rows($rows);
        }
        return $batch_id;
    }

    /**
     * Enqueue every post matching a filter spec. Streams IDs server-side so
     * we never ship a 60k array of IDs over the wire.
     *
     * @param array{post_type:string,status?:string,q?:string,preset?:string} $filter
     * @param array<int, string> $fields
     * @return array{batch_id:string,count:int,truncated:bool}
     */
    public function enqueue_from_filter(array $filter, int $template_id, array $fields, string $mode = 'apply'): array
    {
        global $wpdb;
        $mode = self::normalize_mode($mode);
        $batch_id = wp_generate_uuid4();
        $now = current_time('mysql');
        $fields_json = wp_json_encode(array_values($fields));

        $cap = (int) apply_filters('seocp_bulk_max_matching', self::FILTER_MODE_HARD_CAP);

        $base_args = PostsController::build_query_args(
            (string) ($filter['post_type'] ?? ''),
            $filter
        );
        $base_args['fields']         = 'ids';
        $base_args['posts_per_page'] = self::ID_PAGE_SIZE;
        $base_args['orderby']        = 'ID';
        $base_args['order']          = 'ASC';

        $page  = 1;
        $count = 0;
        $truncated = false;
        $rows  = [];

        do {
            $args = $base_args;
            $args['paged']         = $page;
            $args['no_found_rows'] = true; // we don't need totals here
            $query = new \WP_Query($args);
            $ids   = $query->posts;
            if (!$ids) break;

            foreach ($ids as $pid) {
                if ($count >= $cap) { $truncated = true; break 2; }
                $rows[] = $wpdb->prepare(
                    '(%s, %d, %d, %s, %s, %s, %d, %s)',
                    $batch_id, (int) $pid, $template_id, $fields_json, $mode, 'pending', 0, $now
                );
                $count++;
                if (count($rows) >= self::INSERT_CHUNK) {
                    $this->insert_rows($rows);
                    $rows = [];
                }
            }

            if (count($ids) < self::ID_PAGE_SIZE) break;
            $page++;
        } while (true);

        if ($rows) {
            $this->insert_rows($rows);
        }

        return ['batch_id' => $batch_id, 'count' => $count, 'truncated' => $truncated];
    }

    private static function normalize_mode(string $mode): string
    {
        return $mode === 'review' ? 'review' : 'apply';
    }

    /**
     * Cron worker: process up to N pending jobs per tick.
     */
    public function run_due_batches(): void
    {
        $this->drain(null, (int) apply_filters('seocp_bulk_batch_size', 5));
    }

    /**
     * Synchronous tick driven by the admin-side progress poller. Drains a few
     * jobs in one HTTP request so batches advance even when WP-Cron is disabled
     * or the site has no traffic.
     *
     * Returns the number of jobs processed.
     */
    public function tick(int $limit, ?string $batch_id = null): int
    {
        $limit = max(1, min(20, $limit));
        return $this->drain($batch_id, $limit);
    }

    /**
     * Drain up to $limit pending jobs, optionally scoped to a single batch.
     */
    private function drain(?string $batch_id, int $limit): int
    {
        global $wpdb;
        $now = current_time('mysql');
        if ($batch_id !== null) {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE status = %s AND scheduled_for <= %s AND batch_id = %s ORDER BY id ASC LIMIT %d",
                'pending', $now, $batch_id, $limit
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE status = %s AND scheduled_for <= %s ORDER BY id ASC LIMIT %d",
                'pending', $now, $limit
            );
        }
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        foreach ($rows as $row) {
            $this->process_row($row);
        }
        return count($rows);
    }

    private function process_row(array $row): void
    {
        global $wpdb;
        $id = (int) $row['id'];
        $wpdb->update($this->table(), [
            'status'     => 'running',
            'started_at' => current_time('mysql'),
            'attempts'   => (int) $row['attempts'] + 1,
        ], ['id' => $id]);

        $fields    = json_decode((string) $row['fields_picked'], true) ?: [];
        $batch_id  = (string) $row['batch_id'];
        $mode      = self::normalize_mode((string) ($row['mode'] ?? 'apply'));
        $post_id   = (int) $row['post_id'];
        $tpl_id    = (int) $row['template_id'];
        try {
            $proposal = $this->runner->generate($post_id, $tpl_id, $fields, $batch_id);
            if ($mode === 'review') {
                // Store proposals for the user to review/apply later.
                foreach ($proposal['proposal'] as $field_id => $value) {
                    $this->segments->create($post_id, $tpl_id, $batch_id, (string) $field_id, (string) $value);
                }
            } else {
                $this->runner->apply($post_id, $tpl_id, $proposal['proposal'], $batch_id);
            }
            $wpdb->update($this->table(), [
                'status'       => 'completed',
                'completed_at' => current_time('mysql'),
            ], ['id' => $id]);
        } catch (\Throwable $e) {
            $this->logger->error('Bulk job failed', ['id' => $id, 'msg' => $e->getMessage()]);
            $attempts = (int) $row['attempts'] + 1;
            $next_status = $attempts >= 3 ? 'failed' : 'pending';
            $reschedule  = $attempts >= 3 ? null : current_time('mysql');
            $update = ['status' => $next_status, 'error_message' => $e->getMessage()];
            if ($reschedule) {
                $update['scheduled_for'] = $reschedule;
            } else {
                $update['completed_at'] = current_time('mysql');
            }
            $wpdb->update($this->table(), $update, ['id' => $id]);
        }
    }

    /**
     * Lists the most recent batches with aggregated counts. Used by the
     * Bulk Wizard "Recent batches" panel so users can revisit past runs after
     * navigating away.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recent_batches(int $limit = 10): array
    {
        global $wpdb;
        $limit = max(1, min(50, $limit));
        $sql = $wpdb->prepare(
            "SELECT batch_id,
                    MIN(scheduled_for) AS started_at,
                    MAX(COALESCE(completed_at, started_at, scheduled_for)) AS last_activity_at,
                    COUNT(*)                            AS total,
                    SUM(status = 'completed')           AS completed,
                    SUM(status = 'failed')              AS failed,
                    SUM(status = 'pending')             AS pending,
                    SUM(status = 'running')             AS running,
                    SUM(status = 'cancelled')           AS cancelled,
                    MAX(template_id)                    AS template_id
             FROM {$this->table()}
             GROUP BY batch_id
             ORDER BY MAX(id) DESC
             LIMIT %d",
            $limit
        );
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'batch_id'         => (string) $r['batch_id'],
                'started_at'       => (string) $r['started_at'],
                'last_activity_at' => (string) $r['last_activity_at'],
                'total'            => (int) $r['total'],
                'completed'        => (int) $r['completed'],
                'failed'           => (int) $r['failed'],
                'pending'          => (int) $r['pending'],
                'running'          => (int) $r['running'],
                'cancelled'        => (int) $r['cancelled'],
                'template_id'      => (int) $r['template_id'],
            ];
        }
        return $out;
    }

    /**
     * Cancels every pending job in a batch. Already-completed jobs are NOT
     * rolled back — their changes are already written to the post. Running
     * jobs (AI call in flight) are left to finish their current iteration so
     * we don't orphan a half-applied apply call.
     *
     * Returns the number of jobs cancelled.
     */
    public function stop_batch(string $batch_id): int
    {
        global $wpdb;
        $now = current_time('mysql');
        $cancelled = (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table()}
                SET status = %s, completed_at = %s
              WHERE batch_id = %s AND status = %s",
            'cancelled', $now, $batch_id, 'pending'
        ));
        return $cancelled;
    }

    public function progress(string $batch_id): array
    {
        global $wpdb;

        // Queue-level lifecycle (worker state).
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT status, COUNT(*) AS n FROM {$this->table()} WHERE batch_id = %s GROUP BY status", $batch_id),
            ARRAY_A
        ) ?: [];
        $out = ['pending' => 0, 'running' => 0, 'completed' => 0, 'failed' => 0, 'cancelled' => 0];
        foreach ($rows as $r) {
            $out[(string) $r['status']] = (int) $r['n'];
        }
        $out['total'] = array_sum($out);

        // Run-level outcomes — the real source of truth for "did anything get written?".
        // A queue row marked `completed` only means the worker didn't throw; it could
        // have produced an `applied` (wrote ≥1 field) or `noop` (wrote nothing) run.
        $runs_table = Schema::table('runs');
        $run_rows = $wpdb->get_results(
            $wpdb->prepare("SELECT status, COUNT(*) AS n FROM {$runs_table} WHERE batch_id = %s GROUP BY status", $batch_id),
            ARRAY_A
        ) ?: [];
        $writes = ['applied' => 0, 'noop' => 0, 'failed' => 0, 'proposed' => 0];
        foreach ($run_rows as $r) {
            $key = (string) $r['status'];
            if (isset($writes[$key])) {
                $writes[$key] = (int) $r['n'];
            }
        }
        $out['writes'] = $writes;
        return $out;
    }

    /**
     * Bulk-insert pre-prepared row tuples. Each tuple must already be `prepare()`d
     * with the column order (batch_id, post_id, template_id, fields_picked, mode, status, attempts, scheduled_for).
     * @param array<int, string> $rows
     */
    private function insert_rows(array $rows): void
    {
        if (!$rows) return;
        global $wpdb;
        $sql = "INSERT INTO {$this->table()} (batch_id, post_id, template_id, fields_picked, mode, status, attempts, scheduled_for) VALUES " . implode(',', $rows);
        $wpdb->query($sql);
    }
}
