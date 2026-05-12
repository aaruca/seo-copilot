<?php
/**
 * @var string $title
 * @var string $description
 * @var string $url
 */
?>
<div class="fl-serp" data-serp>
    <div class="fl-serp__url" data-serp-url><?php echo esc_html($url ?? ''); ?></div>
    <div class="fl-serp__title" data-serp-title><?php echo esc_html($title ?? ''); ?></div>
    <div class="fl-serp__desc"  data-serp-desc><?php echo esc_html($description ?? ''); ?></div>
</div>
