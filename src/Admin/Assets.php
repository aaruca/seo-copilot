<?php

namespace SeoCopilot\Admin;

class Assets
{
    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue(string $hook): void
    {
        $is_seocp = (
            strpos((string) $hook, 'seo-copilot') !== false
            || $this->is_post_edit_screen()
        );

        if (!$is_seocp) {
            return;
        }

        // Cache-bust by file mtime instead of plugin version. Plugin version is
        // pinned at 1.0.0 (see feedback memory), so it can't drive cache-bust.
        wp_enqueue_style('seocp-tokens', SEOCP_URL . 'assets/css/fluent-tokens.css', [], $this->v('assets/css/fluent-tokens.css'));
        wp_enqueue_style('seocp-components', SEOCP_URL . 'assets/css/fluent-components.css', ['seocp-tokens'], $this->v('assets/css/fluent-components.css'));
        wp_enqueue_style('seocp-app', SEOCP_URL . 'assets/css/app.css', ['seocp-components'], $this->v('assets/css/app.css'));

        wp_enqueue_script('seocp-app', SEOCP_URL . 'assets/js/app.js', [], $this->v('assets/js/app.js'), true);
        wp_enqueue_script('seocp-rest', SEOCP_URL . 'assets/js/lib/rest.js', ['seocp-app'], $this->v('assets/js/lib/rest.js'), true);
        wp_enqueue_script('seocp-toast', SEOCP_URL . 'assets/js/lib/toast.js', ['seocp-app'], $this->v('assets/js/lib/toast.js'), true);
        wp_enqueue_script('seocp-pivot', SEOCP_URL . 'assets/js/lib/pivot.js', ['seocp-app'], $this->v('assets/js/lib/pivot.js'), true);
        wp_enqueue_script('seocp-widgets', SEOCP_URL . 'assets/js/lib/widgets.js', ['seocp-app'], $this->v('assets/js/lib/widgets.js'), true);

        wp_localize_script('seocp-app', 'seocpData', [
            'restBase'   => esc_url_raw(rest_url(SEOCP_REST_NS)),
            'nonce'      => wp_create_nonce('wp_rest'),
            'adminUrl'   => esc_url_raw(admin_url('admin.php')),
            'pluginUrl'  => SEOCP_URL,
            'version'    => SEOCP_VERSION,
            // Detected SEO plugin (matches the detection in Plugin::boot()).
            // Used by the Bulk Wizard to show a Rank-Math-specific note about
            // its "SEO Details" column showing N/A after bulk writes.
            'seoPlugin'  => $this->detect_seo_plugin(),
            'i18n'       => [
                'generating'    => __('Generating…', 'seo-copilot'),
                'generated'     => __('Proposal ready.', 'seo-copilot'),
                'applied'       => __('Applied.', 'seo-copilot'),
                'failed'        => __('Failed', 'seo-copilot'),
                'no_fields'     => __('No fields selected.', 'seo-copilot'),
            ],
        ]);

        $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
        switch ($page) {
            case AdminMenu::SLUG_OPTIMIZE:
                wp_enqueue_script('seocp-page-smart', SEOCP_URL . 'assets/js/pages/smart-optimizer.js', ['seocp-rest', 'seocp-toast', 'seocp-widgets'], $this->v('assets/js/pages/smart-optimizer.js'), true);
                break;
            case AdminMenu::SLUG_BULK:
                wp_enqueue_script('seocp-page-bulk', SEOCP_URL . 'assets/js/pages/bulk-wizard.js', ['seocp-rest', 'seocp-toast', 'seocp-widgets'], $this->v('assets/js/pages/bulk-wizard.js'), true);
                break;
            case AdminMenu::SLUG_REVIEW:
                wp_enqueue_script('seocp-page-review', SEOCP_URL . 'assets/js/pages/pending-review.js', ['seocp-rest', 'seocp-toast', 'seocp-widgets'], $this->v('assets/js/pages/pending-review.js'), true);
                break;
            case AdminMenu::SLUG_TEMPLATES:
                wp_enqueue_script('seocp-page-templates', SEOCP_URL . 'assets/js/pages/templates-edit.js', ['seocp-rest', 'seocp-toast'], $this->v('assets/js/pages/templates-edit.js'), true);
                wp_enqueue_script('seocp-page-templates-list', SEOCP_URL . 'assets/js/pages/templates-list.js', ['seocp-rest', 'seocp-toast'], $this->v('assets/js/pages/templates-list.js'), true);
                break;
            case AdminMenu::SLUG_SETTINGS:
                wp_enqueue_script('seocp-page-settings', SEOCP_URL . 'assets/js/pages/settings.js', ['seocp-rest', 'seocp-toast'], $this->v('assets/js/pages/settings.js'), true);
                break;
        }

        if ($this->is_post_edit_screen()) {
            wp_enqueue_script('seocp-metabox', SEOCP_URL . 'assets/js/pages/metabox.js', ['seocp-rest', 'seocp-toast', 'seocp-widgets'], $this->v('assets/js/pages/metabox.js'), true);
        }
    }

    /** Returns the file mtime (or plugin version as fallback) for cache-busting. */
    private function v(string $relative): string
    {
        $path = SEOCP_DIR . ltrim($relative, '/');
        $mtime = file_exists($path) ? @filemtime($path) : 0;
        return $mtime ? (string) $mtime : SEOCP_VERSION;
    }

    /** Mirrors the field-provider detection in Plugin::boot(). */
    private function detect_seo_plugin(): string
    {
        if (defined('RANK_MATH_VERSION') || class_exists('RankMath')) return 'rank_math';
        if (defined('WPSEO_VERSION') || class_exists('WPSEO_Options')) return 'yoast';
        if (defined('AIOSEO_VERSION') || function_exists('aioseo')) return 'aioseo';
        if (defined('SEOPRESS_VERSION') || function_exists('seopress_get_locale') || function_exists('seopress_get_service')) return 'seopress';
        return 'none';
    }

    private function is_post_edit_screen(): bool
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        return $screen && $screen->base === 'post';
    }
}
