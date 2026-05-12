<?php
/**
 * @var array<int, array{key:string,label:string,hint?:string}> $steps
 * @var int $active   Zero-based index of the active step.
 */
$active = isset($active) ? (int) $active : 0;
?>
<nav class="fl-stepper" aria-label="<?php esc_attr_e('Wizard steps', 'seo-copilot'); ?>">
    <?php foreach ($steps as $i => $step):
        $state = $i < $active ? 'done' : ($i === $active ? 'active' : 'pending');
    ?>
        <button type="button"
                class="fl-stepper__step js-step-jump"
                data-step="<?php echo (int) $i; ?>"
                data-state="<?php echo esc_attr($state); ?>"
                <?php if ($state === 'pending'): ?>aria-disabled="true" disabled<?php endif; ?>>
            <span class="fl-stepper__num"><span><?php echo (int) ($i + 1); ?></span></span>
            <span style="min-width:0;">
                <span class="fl-stepper__label"><?php echo esc_html($step['label']); ?></span>
                <?php if (!empty($step['hint'])): ?>
                    <span class="fl-stepper__hint"><?php echo esc_html($step['hint']); ?></span>
                <?php endif; ?>
            </span>
        </button>
    <?php endforeach; ?>
</nav>
