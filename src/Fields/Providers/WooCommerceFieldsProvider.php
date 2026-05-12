<?php

namespace SeoCopilot\Fields\Providers;

use SeoCopilot\Fields\Field;
use SeoCopilot\Fields\FieldRegistry;

class WooCommerceFieldsProvider
{
    public function register(FieldRegistry $registry): void
    {
        $registry->register(new Field(
            'wc_short_description',
            __('Product short description', 'seo-copilot'),
            'content',
            static function (int $id): string {
                $p = get_post($id);
                return $p ? (string) $p->post_excerpt : '';
            },
            static function (int $id, string $value): bool {
                return (bool) wp_update_post(['ID' => $id, 'post_excerpt' => $value], true);
            },
            ['product'],
            500,
            __('Sits next to the product gallery on single-product pages.', 'seo-copilot')
        ));

        $registry->register(new Field(
            'wc_long_description',
            __('Product long description', 'seo-copilot'),
            'content',
            static function (int $id): string {
                $p = get_post($id);
                return $p ? (string) $p->post_content : '';
            },
            static function (int $id, string $value): bool {
                return (bool) wp_update_post(['ID' => $id, 'post_content' => $value], true);
            },
            ['product'],
            0,
            __('Body of the product page (the editor content).', 'seo-copilot')
        ));
    }
}
