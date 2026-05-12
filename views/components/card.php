<?php
/**
 * @var string $title
 * @var string $subtitle
 * @var callable $body
 * @var bool $elevated
 */
$elevated = isset($elevated) ? (bool) $elevated : false;
?>
<div class="fl-card<?php echo $elevated ? ' fl-card--elevated' : ''; ?>">
    <?php if (!empty($title)): ?>
        <h2 class="fl-card__title"><?php echo esc_html($title); ?></h2>
    <?php endif; ?>
    <?php if (!empty($subtitle)): ?>
        <div class="fl-card__subtitle"><?php echo esc_html($subtitle); ?></div>
    <?php endif; ?>
    <?php if (isset($body) && is_callable($body)) { call_user_func($body); } ?>
</div>
