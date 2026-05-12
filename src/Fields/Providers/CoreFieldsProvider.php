<?php

namespace SeoCopilot\Fields\Providers;

use SeoCopilot\Fields\Field;
use SeoCopilot\Fields\FieldRegistry;

class CoreFieldsProvider
{
    public function register(FieldRegistry $registry): void
    {
        $registry->register(new Field(
            'post_title',
            __('Post title', 'seo-copilot'),
            'content',
            static function (int $id): string {
                $p = get_post($id);
                return $p ? (string) $p->post_title : '';
            },
            static function (int $id, string $value): bool {
                return (bool) wp_update_post(['ID' => $id, 'post_title' => $value], true);
            },
            [],
            70,
            __('The post / page / product title.', 'seo-copilot')
        ));

        $registry->register(new Field(
            'post_excerpt',
            __('Excerpt', 'seo-copilot'),
            'content',
            static function (int $id): string {
                $p = get_post($id);
                return $p ? (string) $p->post_excerpt : '';
            },
            static function (int $id, string $value): bool {
                return (bool) wp_update_post(['ID' => $id, 'post_excerpt' => $value], true);
            },
            [],
            300,
            __('Short summary used by themes and feeds.', 'seo-copilot')
        ));

        $registry->register(new Field(
            'post_content',
            __('Post content', 'seo-copilot'),
            'content',
            static function (int $id): string {
                $p = get_post($id);
                return $p ? (string) $p->post_content : '';
            },
            static function (int $id, string $value): bool {
                return (bool) wp_update_post(['ID' => $id, 'post_content' => $value], true);
            },
            [],
            0,
            __('Long-form body. Will overwrite the editor body when ticked.', 'seo-copilot')
        ));

        $registry->register(new Field(
            'featured_image_alt',
            __('Featured image alt', 'seo-copilot'),
            'media',
            static function (int $id): string {
                $thumb = (int) get_post_thumbnail_id($id);
                if (!$thumb) return '';
                return (string) get_post_meta($thumb, '_wp_attachment_image_alt', true);
            },
            static function (int $id, string $value): bool {
                $thumb = (int) get_post_thumbnail_id($id);
                if (!$thumb) return false;
                return (bool) update_post_meta($thumb, '_wp_attachment_image_alt', sanitize_text_field($value));
            },
            [],
            125,
            __('Alt text on the featured / product image.', 'seo-copilot')
        ));
    }
}
