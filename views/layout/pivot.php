<?php
/**
 * @var array<int, array{id:string,label:string}> $tabs
 * @var string $active
 * @var array<string, callable> $panels
 */
?>
<div class="seocp-pivot-host">
    <div class="fl-pivot" role="tablist">
        <?php foreach ($tabs as $tab): $is_active = $tab['id'] === $active; ?>
            <button class="fl-pivot__tab" role="tab"
                aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
                aria-controls="seocp-pivot-<?php echo esc_attr($tab['id']); ?>">
                <?php echo esc_html($tab['label']); ?>
            </button>
        <?php endforeach; ?>
    </div>
    <?php foreach ($tabs as $tab): $is_active = $tab['id'] === $active; ?>
        <div class="fl-pivot__panel" id="seocp-pivot-<?php echo esc_attr($tab['id']); ?>"
             role="tabpanel" data-active="<?php echo $is_active ? 'true' : 'false'; ?>">
            <?php
            if (isset($panels[$tab['id']]) && is_callable($panels[$tab['id']])) {
                call_user_func($panels[$tab['id']]);
            }
            ?>
        </div>
    <?php endforeach; ?>
</div>
