<?php

namespace SeoCopilot\Templates;

use SeoCopilot\Database\Schema;

class TemplateRepository
{
    private function table(): string
    {
        return Schema::table('templates');
    }

    /** @return array<int, Template> */
    public function all(bool $only_active = false): array
    {
        global $wpdb;
        $sql = "SELECT * FROM {$this->table()}";
        if ($only_active) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY name ASC';
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        return array_map(static fn($r) => new Template($r), $rows);
    }

    public function find(int $id): ?Template
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id), ARRAY_A);
        return $row ? new Template($row) : null;
    }

    public function find_by_slug(string $slug): ?Template
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE slug = %s", $slug), ARRAY_A);
        return $row ? new Template($row) : null;
    }

    /** @return array<int, Template> */
    public function for_post_type(string $post_type): array
    {
        $out = [];
        foreach ($this->all(true) as $tpl) {
            if ($tpl->applies_to_post_types === [] || in_array($post_type, $tpl->applies_to_post_types, true)) {
                $out[] = $tpl;
            }
        }
        return $out;
    }

    public function save(Template $tpl): Template
    {
        global $wpdb;
        $now = current_time('mysql');

        $data = [
            'slug'                  => $tpl->slug,
            'name'                  => $tpl->name,
            'description'           => $tpl->description,
            'system_prompt'         => $tpl->system_prompt,
            'user_template'         => $tpl->user_template,
            'json_schema'           => $tpl->json_schema,
            'produces'              => wp_json_encode($tpl->produces),
            'applies_to_post_types' => wp_json_encode($tpl->applies_to_post_types),
            'is_default'            => $tpl->is_default ? 1 : 0,
            'is_active'             => $tpl->is_active ? 1 : 0,
            'updated_at'            => $now,
        ];

        if ($tpl->id) {
            $wpdb->update($this->table(), $data, ['id' => $tpl->id]);
        } else {
            $data['created_at'] = $now;
            $wpdb->insert($this->table(), $data);
            $tpl->id = (int) $wpdb->insert_id;
        }
        return $tpl;
    }

    public function delete(int $id): bool
    {
        global $wpdb;
        return false !== $wpdb->delete($this->table(), ['id' => $id]);
    }
}
