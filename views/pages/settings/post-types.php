<?php
use SeoCopilot\Fields\FieldRegistry;
use SeoCopilot\PostTypes\PostTypeRegistry;

/**
 * @var array<string, string> $available
 * @var array<int, string>    $enabled
 * @var FieldRegistry         $fields
 * @var PostTypeRegistry      $registry
 */
?>
<div class="fl-message-bar">
    <strong><?php esc_html_e('How this works:', 'seo-copilot'); ?></strong>
    <span><?php esc_html_e('Switch on the post types SEO Copilot should manage. Then expand each one and tick the fields you allow it to write. Whatever is unticked is never written, even by an "auto-apply" run.', 'seo-copilot'); ?></span>
</div>

<div id="seocp-pt-list">
<?php foreach ($available as $slug => $label): $on = in_array($slug, $enabled, true); ?>
    <div class="seocp-pt-row" data-collapsed="true" data-pt="<?php echo esc_attr($slug); ?>">
        <div class="seocp-pt-row__head">
            <label class="fl-switch" onclick="event.stopPropagation();">
                <input type="checkbox" class="seocp-pt-toggle" data-pt="<?php echo esc_attr($slug); ?>" <?php checked($on); ?> />
                <span class="fl-switch__track"><span class="fl-switch__thumb"></span></span>
                <span style="font-weight:600;"><?php echo esc_html($label); ?></span>
                <span class="fl-muted">(<?php echo esc_html($slug); ?>)</span>
            </label>
            <button type="button" class="fl-button fl-button--subtle seocp-pt-expand">
                <?php esc_html_e('Configure fields ▾', 'seo-copilot'); ?>
            </button>
        </div>
        <div class="seocp-pt-row__body">
            <?php
            $grouped = $fields->for_post_type($slug);
            $defaults = $registry->field_defaults($slug);
            if (!$grouped):
            ?>
                <p class="fl-muted"><?php esc_html_e('No fields available for this post type yet.', 'seo-copilot'); ?></p>
            <?php else: foreach ($grouped as $group => $items): ?>
                <h4 style="text-transform:uppercase;color:var(--seocp-color-fg-3);font-size:12px;letter-spacing:.04em;">
                    <?php echo esc_html(ucfirst((string) $group)); ?>
                </h4>
                <div class="seocp-field-grid" style="margin-bottom:16px;">
                    <?php foreach ($items as $f):
                        $checked = in_array($f->id, $defaults, true) || !$defaults; ?>
                        <label class="fl-choice" title="<?php echo esc_attr($f->description); ?>">
                            <input type="checkbox"
                                   class="seocp-field-toggle"
                                   data-pt="<?php echo esc_attr($slug); ?>"
                                   data-field="<?php echo esc_attr($f->id); ?>"
                                   <?php checked($checked); ?> />
                            <span><?php echo esc_html($f->label); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; endif; ?>
            <div>
                <button type="button" class="fl-button fl-button--primary seocp-save-defaults" data-pt="<?php echo esc_attr($slug); ?>">
                    <?php esc_html_e('Save field selection', 'seo-copilot'); ?>
                </button>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
