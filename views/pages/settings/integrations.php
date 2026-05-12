<?php
/** @var bool $wc, $rm, $yoast, $aioseo, $seopress, $bricks */
$rows = [
    ['name' => 'WooCommerce',    'on' => $wc,       'note' => __('Adds product short / long description writers.', 'seo-copilot')],
    ['name' => 'Rank Math',      'on' => $rm,       'note' => __('Adds rank_math_title / description / focus_keyword writers.', 'seo-copilot')],
    ['name' => 'Yoast SEO',      'on' => $yoast,    'note' => __('Adds _yoast_wpseo_title / metadesc / focuskw writers.', 'seo-copilot')],
    ['name' => 'All in One SEO', 'on' => $aioseo,   'note' => __('Adds _aioseo_title / description / keyphrases writers.', 'seo-copilot')],
    ['name' => 'SEOPress',       'on' => $seopress, 'note' => __('Adds _seopress_titles_title / _seopress_titles_desc / _seopress_analysis_target_kw writers (multi-keyword, comma-separated).', 'seo-copilot')],
    ['name' => 'Bricks Builder', 'on' => $bricks,   'note' => __('Builder content is exposed as {{builder_plain_text}} in prompts.', 'seo-copilot')],
];
?>
<div class="fl-card">
    <h2 class="fl-card__title"><?php esc_html_e('Detected integrations', 'seo-copilot'); ?></h2>
    <p class="fl-muted"><?php esc_html_e('Each integration is auto-loaded only when the corresponding plugin is active.', 'seo-copilot'); ?></p>
    <table class="fl-data-grid">
        <thead><tr>
            <th><?php esc_html_e('Plugin', 'seo-copilot'); ?></th>
            <th><?php esc_html_e('Status', 'seo-copilot'); ?></th>
            <th><?php esc_html_e('Notes', 'seo-copilot'); ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><strong><?php echo esc_html($r['name']); ?></strong></td>
                <td>
                    <?php if ($r['on']): ?>
                        <span class="fl-badge fl-badge--success"><?php esc_html_e('Active', 'seo-copilot'); ?></span>
                    <?php else: ?>
                        <span class="fl-badge"><?php esc_html_e('Not detected', 'seo-copilot'); ?></span>
                    <?php endif; ?>
                </td>
                <td class="fl-muted"><?php echo esc_html($r['note']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
