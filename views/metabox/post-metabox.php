<?php
use SeoCopilot\Templates\Template;

/**
 * @var WP_Post              $post
 * @var array<int, Template> $templates
 * @var array<int, string>   $defaults
 * @var array<string, array> $grouped
 */
?>
<div class="seocp-app seocp-metabox-host">
    <div id="seocp-metabox" data-post="<?php echo (int) $post->ID; ?>" data-pt="<?php echo esc_attr($post->post_type); ?>">
        <?php if (!$templates): ?>
            <p class="fl-muted"><?php esc_html_e('No templates available for this post type yet.', 'seo-copilot'); ?></p>
        <?php else: ?>
            <div class="fl-field">
                <label class="fl-field__label"><?php esc_html_e('Template', 'seo-copilot'); ?></label>
                <select id="seocp-mb-tpl" class="fl-select">
                    <?php foreach ($templates as $t): ?>
                        <option value="<?php echo (int) $t->id; ?>"
                            data-produces="<?php echo esc_attr(implode(',', $t->produces)); ?>"
                        ><?php echo esc_html($t->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="seocp-mb-fields" class="fl-stack"></div>
            <div class="fl-row" style="margin-top:8px;">
                <button id="seocp-mb-generate" type="button" class="fl-button fl-button--primary fl-button--small"><?php esc_html_e('Generate', 'seo-copilot'); ?></button>
                <span id="seocp-mb-status" class="fl-muted"></span>
            </div>
            <div id="seocp-mb-output" class="fl-stack" style="margin-top:8px;"></div>
        <?php endif; ?>
    </div>
</div>
