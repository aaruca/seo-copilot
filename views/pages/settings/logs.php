<?php
/**
 * @var array $totals
 * @var array $recent
 */
?>
<div class="fl-grid-3" style="margin-bottom:20px;">
    <div class="fl-card">
        <div class="fl-muted"><?php esc_html_e('Runs (lifetime)', 'seo-copilot'); ?></div>
        <div style="font-size:28px;font-weight:600;"><?php echo (int) $totals['count']; ?></div>
    </div>
    <div class="fl-card">
        <div class="fl-muted"><?php esc_html_e('Total tokens', 'seo-copilot'); ?></div>
        <div style="font-size:20px;font-weight:600;">
            <?php echo number_format_i18n((int) $totals['tokens_in']); ?> in / <?php echo number_format_i18n((int) $totals['tokens_out']); ?> out
        </div>
    </div>
    <div class="fl-card">
        <div class="fl-muted"><?php esc_html_e('Total cost', 'seo-copilot'); ?></div>
        <div style="font-size:28px;font-weight:600;">$<?php echo esc_html(number_format((float) $totals['cost'], 4)); ?></div>
    </div>
</div>

<div class="fl-card">
    <div class="fl-row">
        <h2 class="fl-card__title" style="margin:0;flex:1;"><?php esc_html_e('Last 200 runs', 'seo-copilot'); ?></h2>
        <button id="seocp-truncate-runs" type="button" class="fl-button fl-button--danger fl-button--small">
            <?php esc_html_e('Truncate logs', 'seo-copilot'); ?>
        </button>
    </div>
    <div class="fl-divider"></div>
    <table class="fl-data-grid">
        <thead><tr>
            <th><?php esc_html_e('When', 'seo-copilot'); ?></th>
            <th><?php esc_html_e('Post', 'seo-copilot'); ?></th>
            <th><?php esc_html_e('Type', 'seo-copilot'); ?></th>
            <th><?php esc_html_e('Status', 'seo-copilot'); ?></th>
            <th><?php esc_html_e('Tokens', 'seo-copilot'); ?></th>
            <th><?php esc_html_e('Cost', 'seo-copilot'); ?></th>
            <th><?php esc_html_e('Fields', 'seo-copilot'); ?></th>
            <th><?php esc_html_e('Error', 'seo-copilot'); ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($recent as $r): ?>
            <tr>
                <td><?php echo esc_html($r['created_at']); ?></td>
                <td><?php echo esc_html(get_the_title((int) $r['post_id']) ?: ('#' . (int) $r['post_id'])); ?></td>
                <td><?php echo esc_html((string) $r['post_type']); ?></td>
                <td><span class="fl-badge"><?php echo esc_html((string) $r['status']); ?></span></td>
                <td><?php echo (int) $r['tokens_in']; ?> / <?php echo (int) $r['tokens_out']; ?></td>
                <td>$<?php echo esc_html(number_format((float) $r['cost'], 4)); ?></td>
                <td class="fl-muted"><?php echo esc_html(implode(', ', (array) $r['fields_written'])); ?></td>
                <td class="fl-muted"><?php echo esc_html((string) ($r['error_message'] ?? '')); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
$cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
$next_tick     = wp_next_scheduled('seocp_run_bulk_batch');
$next_label    = $next_tick ? human_time_diff(time(), $next_tick) . ' (' . wp_date('Y-m-d H:i:s', $next_tick) . ')' : __('Not scheduled', 'seo-copilot');
$alt_cron      = defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON;
?>
<div class="fl-card" style="margin-top:20px;">
    <h2 class="fl-card__title"><?php esc_html_e('Diagnostics', 'seo-copilot'); ?></h2>
    <?php if ($cron_disabled): ?>
        <div class="fl-message-bar fl-message-bar--warning">
            <strong><?php esc_html_e('WP-Cron is disabled', 'seo-copilot'); ?></strong>
            <span><?php esc_html_e('Your wp-config.php has DISABLE_WP_CRON. Bulk batches will only advance while a Bulk Wizard tab is open (in-page worker), or when an external cron hits wp-cron.php on a schedule.', 'seo-copilot'); ?></span>
        </div>
    <?php endif; ?>
    <table class="fl-data-grid">
        <tbody>
            <tr><th><?php esc_html_e('PHP version', 'seo-copilot'); ?></th><td><?php echo esc_html(PHP_VERSION); ?></td></tr>
            <tr><th><?php esc_html_e('WordPress version', 'seo-copilot'); ?></th><td><?php echo esc_html(get_bloginfo('version')); ?></td></tr>
            <tr><th><?php esc_html_e('Plugin version', 'seo-copilot'); ?></th><td><?php echo esc_html(SEOCP_VERSION); ?></td></tr>
            <tr><th><?php esc_html_e('REST namespace', 'seo-copilot'); ?></th><td class="fl-mono"><?php echo esc_html(SEOCP_REST_NS); ?></td></tr>
            <tr><th><?php esc_html_e('WP-Cron status', 'seo-copilot'); ?></th><td>
                <?php if ($cron_disabled): ?>
                    <span class="fl-badge fl-badge--warning"><?php esc_html_e('DISABLE_WP_CRON = true', 'seo-copilot'); ?></span>
                <?php else: ?>
                    <span class="fl-badge fl-badge--success"><?php esc_html_e('Enabled', 'seo-copilot'); ?></span>
                <?php endif; ?>
                <?php if ($alt_cron): ?>
                    <span class="fl-badge fl-badge--info" style="margin-left:6px;"><?php esc_html_e('ALTERNATE_WP_CRON', 'seo-copilot'); ?></span>
                <?php endif; ?>
            </td></tr>
            <tr><th><?php esc_html_e('Next bulk worker tick', 'seo-copilot'); ?></th><td>
                <?php if ($next_tick): ?>
                    <span class="fl-badge fl-badge--success"><?php esc_html_e('Scheduled', 'seo-copilot'); ?></span>
                    <span class="fl-muted" style="margin-left:6px;"><?php echo esc_html($next_label); ?></span>
                <?php else: ?>
                    <span class="fl-badge fl-badge--danger"><?php esc_html_e('Not scheduled', 'seo-copilot'); ?></span>
                    <span class="fl-muted" style="margin-left:6px;"><?php esc_html_e('Deactivate and reactivate the plugin to re-register.', 'seo-copilot'); ?></span>
                <?php endif; ?>
            </td></tr>
            <tr><th><?php esc_html_e('WooCommerce', 'seo-copilot'); ?></th><td><?php echo class_exists('WooCommerce') ? esc_html__('Active', 'seo-copilot') : esc_html__('Not detected', 'seo-copilot'); ?></td></tr>
            <tr><th><?php esc_html_e('Rank Math', 'seo-copilot'); ?></th><td><?php echo (defined('RANK_MATH_VERSION') || class_exists('RankMath')) ? esc_html__('Active', 'seo-copilot') : esc_html__('Not detected', 'seo-copilot'); ?></td></tr>
            <tr><th><?php esc_html_e('Yoast SEO', 'seo-copilot'); ?></th><td><?php echo (defined('WPSEO_VERSION') || class_exists('WPSEO_Options')) ? esc_html__('Active', 'seo-copilot') : esc_html__('Not detected', 'seo-copilot'); ?></td></tr>
            <tr><th><?php esc_html_e('All in One SEO', 'seo-copilot'); ?></th><td><?php echo (defined('AIOSEO_VERSION') || function_exists('aioseo')) ? esc_html__('Active', 'seo-copilot') : esc_html__('Not detected', 'seo-copilot'); ?></td></tr>
            <tr><th><?php esc_html_e('SEOPress', 'seo-copilot'); ?></th><td><?php echo (defined('SEOPRESS_VERSION') || function_exists('seopress_get_locale') || function_exists('seopress_get_service')) ? esc_html__('Active', 'seo-copilot') : esc_html__('Not detected', 'seo-copilot'); ?></td></tr>
            <tr><th><?php esc_html_e('Bricks', 'seo-copilot'); ?></th><td><?php echo (defined('BRICKS_VERSION') || class_exists('\\Bricks\\Database')) ? esc_html__('Active', 'seo-copilot') : esc_html__('Not detected', 'seo-copilot'); ?></td></tr>
        </tbody>
    </table>
</div>
