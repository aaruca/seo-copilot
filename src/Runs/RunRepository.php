<?php

namespace SeoCopilot\Runs;

use SeoCopilot\Database\Schema;

class RunRepository
{
    private function table(): string
    {
        return Schema::table('runs');
    }

    public function record(Run $run): Run
    {
        global $wpdb;
        $wpdb->insert($this->table(), [
            'post_id'        => $run->post_id,
            'post_type'      => $run->post_type,
            'template_id'    => $run->template_id,
            'status'         => $run->status,
            'fields_written' => wp_json_encode($run->fields_written),
            'tokens_in'      => $run->tokens_in,
            'tokens_out'     => $run->tokens_out,
            'cost'           => $run->cost,
            'model'          => $run->model,
            'error_message'  => $run->error_message,
            'batch_id'       => $run->batch_id,
            'created_at'     => current_time('mysql'),
        ]);
        $run->id = (int) $wpdb->insert_id;
        return $run;
    }

    /** @return array<int, array<string, mixed>> */
    public function recent(int $limit = 200, ?string $batch_id = null): array
    {
        global $wpdb;
        $limit = max(1, min(1000, $limit));
        if ($batch_id) {
            $sql = $wpdb->prepare("SELECT * FROM {$this->table()} WHERE batch_id = %s ORDER BY id DESC LIMIT %d", $batch_id, $limit);
        } else {
            $sql = $wpdb->prepare("SELECT * FROM {$this->table()} ORDER BY id DESC LIMIT %d", $limit);
        }
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        foreach ($rows as &$row) {
            $row['fields_written'] = json_decode((string) $row['fields_written'], true) ?: [];
        }
        return $rows;
    }

    /** @return array{count:int,tokens_in:int,tokens_out:int,cost:float} */
    public function totals(): array
    {
        global $wpdb;
        $row = $wpdb->get_row("SELECT COUNT(*) AS cnt, SUM(tokens_in) AS ti, SUM(tokens_out) AS too, SUM(cost) AS cs FROM {$this->table()}", ARRAY_A);
        return [
            'count'      => (int) ($row['cnt'] ?? 0),
            'tokens_in'  => (int) ($row['ti'] ?? 0),
            'tokens_out' => (int) ($row['too'] ?? 0),
            'cost'       => (float) ($row['cs'] ?? 0),
        ];
    }

    public function truncate(): void
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$this->table()}");
    }
}
