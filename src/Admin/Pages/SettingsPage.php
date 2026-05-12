<?php

namespace SeoCopilot\Admin\Pages;

use SeoCopilot\Fields\FieldRegistry;
use SeoCopilot\Plugin;
use SeoCopilot\PostTypes\PostTypeRegistry;
use SeoCopilot\Providers\OpenAIProvider;
use SeoCopilot\Runs\RunRepository;

class SettingsPage
{
    public static function render(): void
    {
        // Handle Connection form post (rest of pivots use REST/JS).
        if (!empty($_POST['seocp_save_connection'])) {
            check_admin_referer('seocp_settings');
            $current = get_option('seocp_settings', []);
            $current['openai_api_key']  = sanitize_text_field((string) ($_POST['openai_api_key'] ?? ''));
            $current['openai_model']    = sanitize_text_field((string) ($_POST['openai_model'] ?? 'gpt-4.1-mini'));
            $current['rate_per_min']    = max(1, (int) ($_POST['rate_per_min'] ?? 30));
            $current['enable_bricks']   = !empty($_POST['enable_bricks']) ? 1 : 0;
            $current['bricks_char_cap'] = max(500, (int) ($_POST['bricks_char_cap'] ?? 8000));
            $current['business_name']   = sanitize_text_field((string) ($_POST['business_name'] ?? ''));
            $current['geo_city']        = sanitize_text_field((string) ($_POST['geo_city'] ?? ''));
            $current['geo_region']      = sanitize_text_field((string) ($_POST['geo_region'] ?? ''));
            $current['geo_country']     = sanitize_text_field((string) ($_POST['geo_country'] ?? ''));
            $current['geo_service_area']= sanitize_text_field((string) ($_POST['geo_service_area'] ?? ''));
            update_option('seocp_settings', $current);
            add_action('admin_notices', static function () {
                echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'seo-copilot') . '</p></div>';
            });
        }

        $c = Plugin::instance()->container();
        $settings = get_option('seocp_settings', []);
        $tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'connection';

        seocp_partial('layout/page', [
            'title'   => __('Settings', 'seo-copilot'),
            'content' => function () use ($c, $settings, $tab) {
                seocp_partial('layout/pivot', [
                    'tabs' => [
                        ['id' => 'connection',   'label' => __('Connection', 'seo-copilot')],
                        ['id' => 'post-types',   'label' => __('Post Types & Fields', 'seo-copilot')],
                        ['id' => 'integrations', 'label' => __('Integrations', 'seo-copilot')],
                        ['id' => 'logs',         'label' => __('Logs & Diagnostics', 'seo-copilot')],
                    ],
                    'active' => $tab,
                    'panels' => [
                        'connection'   => function () use ($settings) {
                            seocp_partial('pages/settings/connection', [
                                'settings' => $settings,
                                'models'   => OpenAIProvider::models(),
                            ]);
                        },
                        'post-types'   => function () use ($c) {
                            seocp_partial('pages/settings/post-types', [
                                'available' => $c->get(PostTypeRegistry::class)->available(),
                                'enabled'   => $c->get(PostTypeRegistry::class)->enabled(),
                                'fields'    => $c->get(FieldRegistry::class),
                                'registry'  => $c->get(PostTypeRegistry::class),
                            ]);
                        },
                        'integrations' => function () {
                            seocp_partial('pages/settings/integrations', [
                                'wc'       => class_exists('WooCommerce'),
                                'rm'       => defined('RANK_MATH_VERSION') || class_exists('RankMath'),
                                'yoast'    => defined('WPSEO_VERSION') || class_exists('WPSEO_Options'),
                                'aioseo'   => defined('AIOSEO_VERSION') || function_exists('aioseo'),
                                'seopress' => defined('SEOPRESS_VERSION') || function_exists('seopress_get_locale') || function_exists('seopress_get_service'),
                                'bricks'   => defined('BRICKS_VERSION') || class_exists('\\Bricks\\Database'),
                            ]);
                        },
                        'logs'         => function () use ($c) {
                            seocp_partial('pages/settings/logs', [
                                'totals' => $c->get(RunRepository::class)->totals(),
                                'recent' => $c->get(RunRepository::class)->recent(200),
                            ]);
                        },
                    ],
                ]);
            },
        ]);
    }
}
