<?php

namespace SeoCopilot\Fields;

class FieldRegistry
{
    /** @var array<string, Field> */
    private array $fields = [];

    public function register(Field $field): void
    {
        $this->fields[$field->id] = $field;
    }

    public function has(string $id): bool
    {
        return isset($this->fields[$id]);
    }

    public function get(string $id): ?Field
    {
        return $this->fields[$id] ?? null;
    }

    /** @return array<string, Field> */
    public function all(): array
    {
        return $this->fields;
    }

    /**
     * Returns fields available for a given post type, grouped.
     * @return array<string, array<int, Field>>
     */
    public function for_post_type(string $post_type): array
    {
        $grouped = [];
        foreach ($this->fields as $field) {
            if (!$field->applies_to($post_type)) {
                continue;
            }
            $grouped[$field->group][] = $field;
        }
        return $grouped;
    }

    /**
     * Returns the union of all field ids across post types — used by template `produces[]` picker.
     * @return array<int, Field>
     */
    public function union(): array
    {
        return array_values($this->fields);
    }
}
