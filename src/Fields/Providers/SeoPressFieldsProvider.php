<?php

namespace SeoCopilot\Fields\Providers;

use SeoCopilot\Fields\Field;
use SeoCopilot\Fields\FieldRegistry;

/**
 * SEOPress (wp-seopress) field provider.
 *
 * Meta keys (verified against wp-seopress 9.8.5 source):
 *   _seopress_titles_title         — SEO title used in <title> rendering
 *   _seopress_titles_desc          — meta description
 *   _seopress_analysis_target_kw   — target keywords (comma-separated, multi-keyword)
 *
 * Like Rank Math, SEOPress stores multiple focus keywords as a single
 * comma-separated string in one meta key — so the writer just normalises
 * whitespace + de-dupes and stores the raw string.
 */
class SeoPressFieldsProvider
{
    public function register(FieldRegistry $registry): void
    {
        $registry->register(new Field(
            'seopress_seo_title',
            __('SEO title (SEOPress)', 'seo-copilot'),
            'seo',
            static fn(int $id): string => (string) get_post_meta($id, '_seopress_titles_title', true),
            static fn(int $id, string $v): bool => (bool) update_post_meta($id, '_seopress_titles_title', sanitize_text_field($v)),
            [],
            70,
            __('Title used by SEOPress when rendering <title>.', 'seo-copilot')
        ));

        $registry->register(new Field(
            'seopress_meta_description',
            __('Meta description (SEOPress)', 'seo-copilot'),
            'seo',
            static fn(int $id): string => (string) get_post_meta($id, '_seopress_titles_desc', true),
            static fn(int $id, string $v): bool => (bool) update_post_meta($id, '_seopress_titles_desc', sanitize_textarea_field($v)),
            [],
            160,
            __('Meta description for SERP snippets.', 'seo-copilot')
        ));

        $registry->register(new Field(
            'seopress_focus_keyword',
            __('Target keywords (SEOPress)', 'seo-copilot'),
            'seo',
            static fn(int $id): string => (string) get_post_meta($id, '_seopress_analysis_target_kw', true),
            static function (int $id, string $v): bool {
                $clean = self::normalize_keyword_list($v);
                return (bool) update_post_meta($id, '_seopress_analysis_target_kw', sanitize_text_field($clean));
            },
            [],
            200,
            __('Comma-separated. First is the primary target; the rest are secondary (SEOPress).', 'seo-copilot')
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
