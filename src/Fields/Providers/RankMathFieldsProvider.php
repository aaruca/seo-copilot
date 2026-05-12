<?php

namespace SeoCopilot\Fields\Providers;

use SeoCopilot\Fields\Field;
use SeoCopilot\Fields\FieldRegistry;

class RankMathFieldsProvider
{
    public function register(FieldRegistry $registry): void
    {
        $registry->register(new Field(
            'rm_seo_title',
            __('SEO title (Rank Math)', 'seo-copilot'),
            'seo',
            static fn(int $id): string => (string) get_post_meta($id, 'rank_math_title', true),
            static fn(int $id, string $v): bool => (bool) update_post_meta($id, 'rank_math_title', sanitize_text_field($v)),
            [],
            70,
            __('Title used by Rank Math when rendering <title>.', 'seo-copilot')
        ));

        $registry->register(new Field(
            'rm_meta_description',
            __('Meta description (Rank Math)', 'seo-copilot'),
            'seo',
            static fn(int $id): string => (string) get_post_meta($id, 'rank_math_description', true),
            static fn(int $id, string $v): bool => (bool) update_post_meta($id, 'rank_math_description', sanitize_textarea_field($v)),
            [],
            160,
            __('Meta description for SERP snippets.', 'seo-copilot')
        ));

        // Rank Math stores multiple focus keywords comma-separated in the same meta key.
        // The first keyword is the primary; the rest are secondary keywords.
        $registry->register(new Field(
            'rm_focus_keyword',
            __('Focus keywords (Rank Math)', 'seo-copilot'),
            'seo',
            static fn(int $id): string => (string) get_post_meta($id, 'rank_math_focus_keyword', true),
            static function (int $id, string $v): bool {
                $clean = self::normalize_keyword_list($v);
                return (bool) update_post_meta($id, 'rank_math_focus_keyword', sanitize_text_field($clean));
            },
            [],
            200,
            __('Comma-separated. First is primary; the rest are secondary (Rank Math).', 'seo-copilot')
        ));
    }

    private static function normalize_keyword_list(string $raw): string
    {
        $parts = array_filter(array_map('trim', preg_split('/[,;]+/', $raw) ?: []));
        $parts = array_map(static fn($p) => preg_replace('/\s+/u', ' ', $p), $parts);
        $parts = array_values(array_unique($parts));
        return implode(', ', $parts);
    }
}
