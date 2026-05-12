<?php

namespace SeoCopilot\PostTypes;

class PostTypeRegistry
{
    private PostTypeProbe $probe;

    public function __construct(PostTypeProbe $probe)
    {
        $this->probe = $probe;
    }

    /** @return array<string, string> */
    public function available(): array
    {
        return $this->probe->discover();
    }

    /** @return array<int, string> */
    public function enabled(): array
    {
        $stored = get_option('seocp_enabled_post_types', []);
        if (!is_array($stored)) {
            return [];
        }
        $available = array_keys($this->available());
        return array_values(array_intersect($stored, $available));
    }

    public function is_enabled(string $post_type): bool
    {
        return in_array($post_type, $this->enabled(), true);
    }

    /** @param array<int, string> $types */
    public function set_enabled(array $types): void
    {
        $available = array_keys($this->available());
        $clean = array_values(array_unique(array_intersect($types, $available)));
        update_option('seocp_enabled_post_types', $clean);
    }

    /**
     * Returns the per-post-type default field allow-list.
     * @return array<int, string>
     */
    public function field_defaults(string $post_type): array
    {
        $all = get_option('seocp_field_defaults', []);
        if (!is_array($all)) {
            return [];
        }
        return isset($all[$post_type]) && is_array($all[$post_type]) ? array_values($all[$post_type]) : [];
    }

    /** @param array<int, string> $field_ids */
    public function set_field_defaults(string $post_type, array $field_ids): void
    {
        $all = get_option('seocp_field_defaults', []);
        if (!is_array($all)) {
            $all = [];
        }
        $all[$post_type] = array_values(array_unique(array_map('strval', $field_ids)));
        update_option('seocp_field_defaults', $all);
    }
}
