<?php

namespace SeoCopilot\Support;

class PostSnapshotFactory
{
    private BricksContentExtractor $bricks;

    public function __construct(BricksContentExtractor $bricks)
    {
        $this->bricks = $bricks;
    }

    /**
     * Produces the placeholder bag used by PromptAssembler.
     *
     * @return array<string, string>
     */
    public function build(int $post_id): array
    {
        $post = get_post($post_id);
        if (!$post) {
            return [];
        }

        $settings = get_option('seocp_settings', []);
        $bricks_on = !empty($settings['enable_bricks']);
        $cap = isset($settings['bricks_char_cap']) ? (int) $settings['bricks_char_cap'] : 8000;

        $builder_text = '';
        if ($bricks_on && $this->bricks->is_active()) {
            $builder_text = $this->bricks->extract($post_id, $cap);
        }

        $thumb = get_post_thumbnail_id($post_id);
        $alt = $thumb ? (string) get_post_meta($thumb, '_wp_attachment_image_alt', true) : '';

        $cats = wp_get_post_terms($post_id, 'category', ['fields' => 'names']);
        $tags = wp_get_post_terms($post_id, 'post_tag', ['fields' => 'names']);
        if (is_wp_error($cats)) $cats = [];
        if (is_wp_error($tags)) $tags = [];

        $price = '';
        $sku = '';
        if ($post->post_type === 'product' && function_exists('wc_get_product')) {
            $product = wc_get_product($post_id);
            if ($product) {
                $price = (string) $product->get_price();
                $sku   = (string) $product->get_sku();
            }
        }

        $body_plain = wp_strip_all_tags($post->post_content);

        $geo_city    = (string) ($settings['geo_city'] ?? '');
        $geo_region  = (string) ($settings['geo_region'] ?? '');
        $geo_country = (string) ($settings['geo_country'] ?? '');
        $geo_service = (string) ($settings['geo_service_area'] ?? '');
        $biz_name    = (string) ($settings['business_name'] ?? '');

        $geo_combined = trim(implode(', ', array_filter([$geo_city, $geo_region, $geo_country])));

        return [
            'post_id'              => (string) $post_id,
            'post_title'           => (string) $post->post_title,
            'post_type'            => (string) $post->post_type,
            'post_status'          => (string) $post->post_status,
            'post_excerpt'         => (string) $post->post_excerpt,
            'post_content'         => $body_plain,
            'permalink'            => (string) get_permalink($post_id),
            'site_name'            => (string) get_bloginfo('name'),
            'site_tagline'         => (string) get_bloginfo('description'),
            'business_name'        => $biz_name !== '' ? $biz_name : (string) get_bloginfo('name'),
            'featured_image_alt'   => $alt,
            'categories'           => implode(', ', $cats),
            'tags'                 => implode(', ', $tags),
            'price'                => $price,
            'sku'                  => $sku,
            'builder_plain_text'   => $builder_text,
            'geo_city'             => $geo_city,
            'geo_region'           => $geo_region,
            'geo_country'          => $geo_country,
            'geo_service_area'     => $geo_service,
            'geo_location'         => $geo_combined,
        ];
    }
}
