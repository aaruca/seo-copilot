<?php
use SeoCopilot\Fields\Field;
use SeoCopilot\Templates\Template;

/**
 * @var Template $template
 * @var array<int, Field>     $fields
 * @var array<string, string> $types
 */
$is_new = empty($template->id);
$bootstrap = wp_json_encode([
    'id'                    => $template->id,
    'slug'                  => $template->slug,
    'name'                  => $template->name,
    'description'           => $template->description,
    'system_prompt'         => $template->system_prompt,
    'user_template'         => $template->user_template,
    'json_schema'           => $template->json_schema,
    'produces'              => $template->produces,
    'applies_to_post_types' => $template->applies_to_post_types,
    'is_active'             => $template->is_active,
]);
?>
<div id="seocp-template-edit" data-bootstrap='<?php echo esc_attr($bootstrap); ?>'>
    <div class="fl-grid-2">
        <div class="fl-field">
            <label class="fl-field__label"><?php esc_html_e('Name', 'seo-copilot'); ?></label>
            <input class="fl-input" name="name" value="<?php echo esc_attr($template->name); ?>" />
        </div>
        <div class="fl-field">
            <label class="fl-field__label"><?php esc_html_e('Slug', 'seo-copilot'); ?></label>
            <input class="fl-input" name="slug" value="<?php echo esc_attr($template->slug); ?>" placeholder="auto" />
        </div>
    </div>

    <div class="fl-field">
        <label class="fl-field__label"><?php esc_html_e('Description', 'seo-copilot'); ?></label>
        <input class="fl-input" name="description" value="<?php echo esc_attr($template->description); ?>" />
    </div>

    <div class="fl-grid-2">
        <div class="fl-field">
            <label class="fl-field__label"><?php esc_html_e('Applies to post types', 'seo-copilot'); ?></label>
            <div class="seocp-field-grid">
                <?php foreach ($types as $slug => $label): ?>
                    <label class="fl-choice">
                        <input type="checkbox" name="applies_to_post_types" value="<?php echo esc_attr($slug); ?>"
                            <?php checked(in_array($slug, $template->applies_to_post_types, true)); ?> />
                        <span><?php echo esc_html($label); ?> <span class="fl-muted">(<?php echo esc_html($slug); ?>)</span></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <span class="fl-field__hint"><?php esc_html_e('Leave all unchecked to apply to every post type.', 'seo-copilot'); ?></span>
        </div>

        <div class="fl-field">
            <label class="fl-field__label"><?php esc_html_e('Produces (allowed fields)', 'seo-copilot'); ?></label>
            <div class="seocp-field-grid">
                <?php foreach ($fields as $f): ?>
                    <label class="fl-choice" title="<?php echo esc_attr($f->description); ?>">
                        <input type="checkbox" name="produces" value="<?php echo esc_attr($f->id); ?>"
                            <?php checked(in_array($f->id, $template->produces, true)); ?> />
                        <span><?php echo esc_html($f->label); ?> <span class="fl-muted">(<?php echo esc_html($f->id); ?>)</span></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="fl-field">
        <label class="fl-field__label"><?php esc_html_e('System prompt', 'seo-copilot'); ?></label>
        <textarea class="fl-textarea" name="system_prompt" rows="6"><?php echo esc_textarea($template->system_prompt); ?></textarea>
    </div>

    <div class="fl-field">
        <label class="fl-field__label"><?php esc_html_e('User template', 'seo-copilot'); ?></label>
        <textarea class="fl-textarea" name="user_template" rows="10"><?php echo esc_textarea($template->user_template); ?></textarea>
        <span class="fl-field__hint">
            <?php esc_html_e('Available placeholders:', 'seo-copilot'); ?>
            <code>{{post_title}}</code>, <code>{{post_content}}</code>, <code>{{post_excerpt}}</code>,
            <code>{{builder_plain_text}}</code>, <code>{{categories}}</code>, <code>{{tags}}</code>,
            <code>{{price}}</code>, <code>{{sku}}</code>, <code>{{site_name}}</code>, <code>{{fields}}</code>
        </span>
    </div>

    <div class="fl-field">
        <label class="fl-choice">
            <input type="checkbox" name="is_active" <?php checked($template->is_active); ?> />
            <span><?php esc_html_e('Active', 'seo-copilot'); ?></span>
        </label>
    </div>

    <div class="fl-row">
        <button id="seocp-template-save" type="button" class="fl-button fl-button--primary"><?php esc_html_e('Save template', 'seo-copilot'); ?></button>
        <a class="fl-button fl-button--subtle" href="<?php echo esc_url(admin_url('admin.php?page=seo-copilot-templates')); ?>"><?php esc_html_e('Back to list', 'seo-copilot'); ?></a>
        <?php if (!$is_new): ?>
            <span class="fl-spacer"></span>
            <button id="seocp-template-delete" type="button" class="fl-button fl-button--danger"><?php esc_html_e('Delete template', 'seo-copilot'); ?></button>
        <?php endif; ?>
    </div>
</div>
