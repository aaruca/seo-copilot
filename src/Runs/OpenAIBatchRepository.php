<?php

namespace SeoCopilot\Runs;

use SeoCopilot\Database\Schema;

/**
 * CRUD for the seocp_openai_batches table — one row per OpenAI Batch API
 * submission. A single internal batch_id may produce several rows when a job
 * exceeds the per-chunk request limit.
 */
class OpenAIBatchRepository
{
    private function table(): string
    {
        return Schema::table('openai_batches');
    }

    /**
     * Plan a chunk: insert a 'draft' row covering a contiguous queue ID range.
     * Returns the openai_batches row id.
     */
    public function plan(string $batch_id, int $chunk_index, int $queue_id_min, int $queue_id_max, int $request_count, string $model): int
    {
        global $wpdb;
        $wpdb->insert($this->table(), [
            'batch_id'      => $batch_id,
            'chunk_index'   => $chunk_index,
            'status'        => 'draft',
            'model'         => $model,
            'queue_id_min'  => $queue_id_min,
            'queue_id_max'  => $queue_id_max,
            'request_count' => $request_count,
            'created_at'    => current_time('mysql'),
        ]);
        return (int) $wpdb->insert_id;
    }

    /**
     * Pick the oldest pending draft so the cron worker can submit it.
     *
     * @return array<string,mixed>|null
     */
    public function next_draft(): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            "SELECT * FROM {$this->table()} WHERE status = 'draft' ORDER BY id ASC LIMIT 1",
            ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function active(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT * FROM {$this->table()}
              WHERE status IN ('submitted','validating','in_progress','finalizing')
              ORDER BY id ASC",
            ARRAY_A
        );
        return $rows ?: [];
    }

    /**
     * Count chunks that currently occupy OpenAI batch-queue capacity. Includes
     * `building` (we're mid-upload) so the concurrency gate doesn't let a second
     * submission race in while the first is still uploading.
     */
    public function count_in_flight(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table()}
              WHERE status IN ('building','submitted','validating','in_progress','finalizing')"
        );
    }

    public function find(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE id = %d",
            $id
        ), ARRAY_A);
        return $row ?: null;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): void
    {
        global $wpdb;
        $wpdb->update($this->table(), $data, ['id' => $id]);
    }

    /**
     * Mark all active rows for a batch_id as cancelled (used when the user
     * stops a batch from the wizard).
     */
    public function cancel_for_batch(string $batch_id): int
    {
        global $wpdb;
        return (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table()}
                SET status = 'cancelled', completed_at = %s
              WHERE batch_id = %s AND status IN ('draft','submitted','validating','in_progress','finalizing')",
            current_time('mysql'), $batch_id
        ));
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function for_batch(string $batch_id): array
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE batch_id = %s ORDER BY chunk_index ASC",
            $batch_id
        ), ARRAY_A);
        return $rows ?: [];
    }
}
