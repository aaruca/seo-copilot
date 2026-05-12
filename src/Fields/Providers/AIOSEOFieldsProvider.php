<?php

namespace SeoCopilot\Fields\Providers;

use SeoCopilot\Fields\Field;
use SeoCopilot\Fields\FieldRegistry;

class AIOSEOFieldsProvider
{
    public function register(FieldRegistry $registry): void
    {
        $registry->register(new Field(
            'aioseo_title',
            __('SEO title (AIOSEO)', 'seo-copilot'),
            'seo',
            static fn(int $id): string => (string) get_post_meta($id, '_aioseo_title', true),
            static fn(int $id, string $v): bool => (bool) update_post_meta($id, '_aioseo_title', sanitize_text_field($v)),
            [],
            70,
            __('Title used by AIOSEO when rendering <title>.', 'seo-copilot')
        ));

        $registry->register(new Field(
            'aioseo_description',
            __('Meta description (AIOSEO)', 'seo-copilot'),
            'seo',
            static fn(int $id): string => (string) get_post_meta($id, '_aioseo_description', true),
            static fn(int $id, string $v): bool => (bool) update_post_meta($id, '_aioseo_description', sanitize_textarea_field($v)),
            [],
            160,
            __('Meta description for SERP snippets.', 'seo-copilot')
        ));

        // AIOSEO stores keyphrases as JSON in `_aioseo_keyphrases`:
        //   {"focus":{"keyphrase":"primary","language":"en"},
        //    "additional":[{"keyphrase":"secondary"}, ...]}
        $registry->register(new Field(
            'aioseo_keyphrase',
            __('Focus keyphrases (AIOSEO)', 'seo-copilot'),
            'seo',
            static function (int $id): string {
                $raw = get_post_meta($id, '_aioseo_keyphrases', true);
                if (!is_string($raw) || $raw === '') return '';
                $decoded = json_decode($raw, true);
                if (!is_array($decoded)) return $raw; // legacy plain string
                $list = [];
                if (isset($decoded['focus']['keyphrase'])) {
                    $list[] = (string) $decoded['focus']['keyphrase'];
                }
                if (isset($decoded['additional']) && is_array($decoded['additional'])) {
                    foreach ($decoded['additional'] as $row) {
                        if (is_array($row) && isset($row['keyphrase'])) {
                            $list[] = (string) $row['keyphrase'];
                        }
                    }
                }
                return implode(', ', array_filter(array_map('trim', $list)));
            },
            static function (int $id, string $v): bool {
                $parts = self::normalize_keyword_list_array($v);
                if (!$parts) {
                    delete_post_meta($id, '_aioseo_keyphrases');
                    return true;
                }
                $payload = [
                    'focus' => ['keyphrase' => $parts[0], 'language' => 'en', 'score' => 0, 'analysis' => new \stdClass()],
                ];
                $extras = array_slice($parts, 1);
                if ($extras) {
                    $payload['additional'] = array_map(static fn($k) => [
                        'keyphrase' => $k, 'language' => 'en', 'score' => 0, 'analysis' => new \stdClass(),
                    ], $extras);
                }
                update_post_meta($id, '_aioseo_keyphrases', wp_json_encode($payload));
                return true;
            },
            [],
            200,
            __('Comma-separated. First is the focus keyphrase; the rest become additional keyphrases.', 'seo-copilot')
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
