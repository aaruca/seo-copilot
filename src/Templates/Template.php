<?php

namespace SeoCopilot\Templates;

class Template
{
    public ?int $id;
    public string $slug;
    public string $name;
    public string $description;
    public string $system_prompt;
    public string $user_template;
    public string $json_schema;
    /** @var array<int, string> */
    public array $produces;
    /** @var array<int, string> */
    public array $applies_to_post_types;
    public bool $is_default;
    public bool $is_active;
    public string $created_at;
    public string $updated_at;

    public function __construct(array $row = [])
    {
        $this->id                    = isset($row['id']) ? (int) $row['id'] : null;
        $this->slug                  = (string) ($row['slug'] ?? '');
        $this->name                  = (string) ($row['name'] ?? '');
        $this->description           = (string) ($row['description'] ?? '');
        $this->system_prompt         = (string) ($row['system_prompt'] ?? '');
        $this->user_template         = (string) ($row['user_template'] ?? '');
        $this->json_schema           = (string) ($row['json_schema'] ?? '');
        $this->produces              = self::decode_array($row['produces'] ?? []);
        $this->applies_to_post_types = self::decode_array($row['applies_to_post_types'] ?? []);
        $this->is_default            = !empty($row['is_default']);
        $this->is_active             = !isset($row['is_active']) || !empty($row['is_active']);
        $this->created_at            = (string) ($row['created_at'] ?? '');
        $this->updated_at            = (string) ($row['updated_at'] ?? '');
    }

    /** @param mixed $value */
    private static function decode_array($value): array
    {
        if (is_array($value)) return array_values(array_map('strval', $value));
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) return array_values(array_map('strval', $decoded));
        }
        return [];
    }

    public function to_array(): array
    {
        return [
            'id'                    => $this->id,
            'slug'                  => $this->slug,
            'name'                  => $this->name,
            'description'           => $this->description,
            'system_prompt'         => $this->system_prompt,
            'user_template'         => $this->user_template,
            'json_schema'           => $this->json_schema,
            'produces'              => $this->produces,
            'applies_to_post_types' => $this->applies_to_post_types,
            'is_default'            => $this->is_default,
            'is_active'             => $this->is_active,
            'created_at'            => $this->created_at,
            'updated_at'            => $this->updated_at,
        ];
    }
}
