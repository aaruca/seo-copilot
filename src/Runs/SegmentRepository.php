<?php

namespace SeoCopilot\Runs;

use SeoCopilot\Database\Schema;

/**
 * Repository for `seocp_segments` — generated proposals waiting for review.
 *
 * approved column convention:
 *   0 = pending review (default)
 *   1 = applied to post
 *   2 = rejected
 */
class SegmentRepository
{
    private function table(): string
    {
        return Schema::table('segments');
    }

    public function create(int $post_id, int $template_id, ?string $batch_id, string $field_id, string $generated_value): int
    {
        global $wpdb;
        $wpdb->insert($this->table(), [
            'batch_id'        => $batch_id,
            'post_id'         => $post_id,
            'template_id'     => $template_id,
            'field_id'        => $field_id,
            'generated_value' => $generated_value,
            'requires_review' => 1,
            'approved'        => 0,
            'generated_at'    => current_time('mysql'),
        ]);
        return (int) $wpdb->insert_id;
    }

    /** @return array<int, array<string, mixed>> */
    public function list_pending(int $limit = 200, int $offset = 0, ?string $batch_id = null, ?string $post_type = null): array
    {
        global $wpdb;
        $limit  = max(1, min(500, $limit));
        $offset = max(0, $offset);
        $t      = $this->table();

        if ($batch_id !== null) {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$t} WHERE approved = 0 AND batch_id = %s ORDER BY post_id ASC, id ASC LIMIT %d OFFSET %d",
                $batch_id, $limit, $offset
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$t} WHERE approved = 0 ORDER BY post_id ASC, id ASC LIMIT %d OFFSET %d",
                $limit, $offset
            );
        }
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        if ($post_type !== null && $post_type !== '') {
            $rows = array_values(array_filter($rows, static function ($r) use ($post_type) {
                return get_post_type((int) $r['post_id']) === $post_type;
            }));
        }
        return $rows;
    }

    public function pending_count(?string $batch_id = null): int
    {
        global $wpdb;
        $t = $this->table();
        if ($batch_id !== null) {
            return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE approved = 0 AND batch_id = %s", $batch_id));
        }
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t} WHERE approved = 0");
    }

    /**
     * Distinct batches that still have pending segments.
     * @return array<int, array{batch_id:string, count:int, oldest:string}>
     */
    public function pending_batches(): array
    {
        global $wpdb;
        $t = $this->table();
        $rows = $wpdb->get_results(
            "SELECT batch_id, COUNT(*) AS cnt, MIN(generated_at) AS oldest
             FROM {$t}
             WHERE approved = 0
             GROUP BY batch_id
             ORDER BY MIN(id) DESC
             LIMIT 50",
            ARRAY_A
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'batch_id' => (string) $r['batch_id'],
                'count'    => (int) $r['cnt'],
                'oldest'   => (string) $r['oldest'],
            ];
        }
        return $out;
    }

    /** @param array<int, int> $ids */
    public function fetch_many(array $ids): array
    {
        global $wpdb;
        if (!$ids) return [];
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $t = $this->table();
        $sql = $wpdb->prepare("SELECT * FROM {$t} WHERE id IN ({$placeholders})", $ids);
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public function mark_applied(int $id): bool
    {
        global $wpdb;
        return false !== $wpdb->update(
            $this->table(),
            ['approved' => 1, 'applied_at' => current_time('mysql')],
            ['id' => $id]
        );
    }

    public function mark_rejected(int $id): bool
    {
        global $wpdb;
        return false !== $wpdb->update(
            $this->table(),
            ['approved' => 2],
            ['id' => $id]
        );
    }

    public function reject_batch(string $batch_id): int
    {
        global $wpdb;
        return (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table()} SET approved = 2 WHERE approved = 0 AND batch_id = %s",
            $batch_id
        ));
    }
}
