<?php
/**
 * @var array $totals
 * @var array $recent
 * @var array $enabled_types
 */
$cost = isset($totals['cost']) ? number_format((float) $totals['cost'], 4) : '0.0000';
?>
<div class="fl-grid-3">
    <div class="fl-card">
        <div class="fl-muted"><?php esc_html_e('Total runs', 'seo-copilot'); ?></div>
        <div style="font-size:32px;font-weight:600;"><?php echo (int) ($totals['count'] ?? 0); ?></div>
    </div>
    <div class="fl-card">
        <div class="fl-muted"><?php esc_html_e('Tokens (in / out)', 'seo-copilot'); ?></div>
        <div style="font-size:24px;font-weight:600;">
            <?php echo number_format_i18n((int) ($totals['tokens_in'] ?? 0)); ?>
            / <?php echo number_format_i18n((int) ($totals['tokens_out'] ?? 0)); ?>
        </div>
    </div>
    <div class="fl-card">
        <div class="fl-muted"><?php esc_html_e('Estimated cost (USD)', 'seo-copilot'); ?></div>
        <div style="font-size:32px;font-weight:600;">$<?php echo esc_html($cost); ?></div>
    </div>
</div>

<hr class="fl-divider" />

<div class="fl-grid-2">
    <?php seocp_partial('components/card', [
        'title' => __('Enabled post types', 'seo-copilot'),
        'body'  => function () use ($enabled_types) {
            if (!$enabled_types) {
                echo '<p class="fl-muted">' . esc_html__('No post types enabled yet. Go to Settings -> Post Types & Fields.', 'seo-copilot') . '</p>';
                return;
            }
            echo '<div>';
            foreach ($enabled_types as $pt) {
                echo '<span class="fl-tag" style="margin:2px 4px;">' . esc_html($pt) . '</span>';
            }
            echo '</div>';
        },
    ]); ?>

    <?php seocp_partial('components/card', [
        'title' => __('Recent runs', 'seo-copilot'),
        'body'  => function () use ($recent) {
            if (!$recent) {
                echo '<p class="fl-muted">' . esc_html__('Nothing yet. Run the Smart Optimizer to start.', 'seo-copilot') . '</p>';
                return;
            }
            echo '<table class="fl-data-grid"><thead><tr>';
            echo '<th>' . esc_html__('When', 'seo-copilot') . '</th>';
            echo '<th>' . esc_html__('Post', 'seo-copilot') . '</th>';
            echo '<th>' . esc_html__('Status', 'seo-copilot') . '</th>';
            echo '<th>' . esc_html__('Cost', 'seo-copilot') . '</th>';
            echo '</tr></thead><tbody>';
            foreach ($recent as $r) {
                $title = get_the_title((int) $r['post_id']);
                echo '<tr>';
                echo '<td>' . esc_html($r['created_at']) . '</td>';
                echo '<td>' . esc_html($title ?: '#' . (int) $r['post_id']) . '</td>';
                echo '<td><span class="fl-badge fl-badge--' . esc_attr(self_dashboard_status_class((string) $r['status'])) . '">' . esc_html((string) $r['status']) . '</span></td>';
                echo '<td>$' . esc_html(number_format((float) $r['cost'], 4)) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        },
    ]); ?>
</div>
<?php

if (!function_exists('self_dashboard_status_class')) {
    function self_dashboard_status_class(string $s): string
    {
        switch ($s) {
            case 'applied':   return 'success';
            case 'proposed':  return 'info';
            case 'failed':    return 'danger';
            case 'noop':      return 'warning';
        }
        return 'info';
    }
}
