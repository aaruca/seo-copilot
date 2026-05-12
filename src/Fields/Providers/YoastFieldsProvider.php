<?php

namespace SeoCopilot\Fields\Providers;

use SeoCopilot\Fields\Field;
use SeoCopilot\Fields\FieldRegistry;

class YoastFieldsProvider
{
    public function register(FieldRegistry $registry): void
    {
        $registry->register(new Field(
            'yoast_seo_title',
            __('SEO title (Yoast)', 'seo-copilot'),
            'seo',
            static fn(int $id): string => (string) get_post_meta($id, '_yoast_wpseo_title', true),
            static fn(int $id, string $v): bool => (bool) update_post_meta($id, '_yoast_wpseo_title', sanitize_text_field($v)),
            [],
            70,
            __('Title used by Yoast when rendering <title>.', 'seo-copilot')
        ));

        $registry->register(new Field(
            'yoast_meta_description',
            __('Meta description (Yoast)', 'seo-copilot'),
            'seo',
            static fn(int $id): string => (string) get_post_meta($id, '_yoast_wpseo_metadesc', true),
            static fn(int $id, string $v): bool => (bool) update_post_meta($id, '_yoast_wpseo_metadesc', sanitize_textarea_field($v)),
            [],
            160,
            __('Meta description for SERP snippets.', 'seo-copilot')
        ));

        // Yoast stores the primary keyphrase in `_yoast_wpseo_focuskw` and additional
        // keyphrases (Premium feature) in `_yoast_wpseo_focuskeywords` as a JSON array
        // of {"keyword": "...", "score": ""} objects. We populate both so RM-quality
        // multi-keyword data lands cleanly regardless of Premium status.
        $registry->register(new Field(
            'yoast_focus_keyword',
            __('Focus keyphrases (Yoast)', 'seo-copilot'),
            'seo',
            static function (int $id): string {
                $primary = (string) get_post_meta($id, '_yoast_wpseo_focuskw', true);
                $extras  = get_post_meta($id, '_yoast_wpseo_focuskeywords', true);
                $list    = [$primary];
                if (is_string($extras) && $extras !== '') {
                    $decoded = json_decode($extras, true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $row) {
                            if (is_array($row) && isset($row['keyword'])) {
                                $list[] = (string) $row['keyword'];
                            }
                        }
                    }
                }
                return implode(', ', array_filter(array_map('trim', $list)));
            },
            static function (int $id, string $v): bool {
                $parts = self::normalize_keyword_list_array($v);
                $primary = $parts[0] ?? '';
                update_post_meta($id, '_yoast_wpseo_focuskw', sanitize_text_field($primary));
                $extras = array_slice($parts, 1);
                if ($extras) {
                    $payload = array_map(static fn($k) => ['keyword' => $k, 'score' => ''], $extras);
                    update_post_meta($id, '_yoast_wpseo_focuskeywords', wp_json_encode($payload));
                } else {
                    delete_post_meta($id, '_yoast_wpseo_focuskeywords');
                }
                return true;
            },
            [],
            200,
            __('Comma-separated. First is the primary keyphrase; the rest go to additional (Yoast Premium).', 'seo-copilot')
        ));
    }

    /** @return array<int, string> */
    private static function normalize_keyword_list_array(string $raw): array
    {
        $parts = array_filter(array_map('trim', preg_split('/[,;]+/', $raw) ?: []));
        $parts = array_map(static fn($p) => preg_replace('/\s+/u', ' ', $p), $parts);
        return array_values(array_unique($parts));
    }
}
