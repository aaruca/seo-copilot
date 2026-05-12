<?php
use SeoCopilot\Templates\Template;

/** @var array<int, Template> $templates */
$base_url = admin_url('admin.php?page=seo-copilot-templates');
?>
<div id="seocp-tpl-list" class="seocp-page">
    <div class="fl-toolbar">
        <a class="fl-button fl-button--primary" href="<?php echo esc_url(add_query_arg('action', 'new', $base_url)); ?>">
            <?php esc_html_e('+ New template', 'seo-copilot'); ?>
        </a>
        <span class="fl-spacer"></span>
        <button id="seocp-tpl-restore" type="button" class="fl-button" title="<?php esc_attr_e('Re-create any default templates that were deleted', 'seo-copilot'); ?>">
            <?php esc_html_e('Restore defaults', 'seo-copilot'); ?>
        </button>
    </div>

    <div class="fl-card">
        <table class="fl-data-grid">
            <thead><tr>
                <th><?php esc_html_e('Name', 'seo-copilot'); ?></th>
                <th><?php esc_html_e('Slug', 'seo-copilot'); ?></th>
                <th><?php esc_html_e('Applies to', 'seo-copilot'); ?></th>
                <th><?php esc_html_e('Produces', 'seo-copilot'); ?></th>
                <th><?php esc_html_e('Status', 'seo-copilot'); ?></th>
                <th style="text-align:right;"><?php esc_html_e('Actions', 'seo-copilot'); ?></th>
            </tr></thead>
            <tbody>
            <?php if (!$templates): ?>
                <tr><td colspan="6" class="fl-muted" style="text-align:center;padding:24px;"><?php esc_html_e('No templates yet. Create one or click "Restore defaults" to seed the starter pack.', 'seo-copilot'); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($templates as $t):
                $edit = add_query_arg(['action' => 'edit', 'id' => (int) $t->id], $base_url);
                ?>
                <tr data-tpl-id="<?php echo (int) $t->id; ?>" data-tpl-name="<?php echo esc_attr($t->name); ?>">
                    <td><strong><?php echo esc_html($t->name); ?></strong>
                        <?php if ($t->is_default): ?><span class="fl-badge fl-badge--info" style="margin-left:6px;"><?php esc_html_e('Default', 'seo-copilot'); ?></span><?php endif; ?>
                        <div class="fl-muted" style="font-size:12px;"><?php echo esc_html($t->description); ?></div>
                    </td>
                    <td class="fl-mono"><?php echo esc_html($t->slug); ?></td>
                    <td class="fl-muted"><?php echo $t->applies_to_post_types ? esc_html(implode(', ', $t->applies_to_post_types)) : esc_html__('Any', 'seo-copilot'); ?></td>
                    <td class="fl-muted"><?php echo esc_html(count($t->produces) . ' ' . _n('field', 'fields', count($t->produces), 'seo-copilot')); ?></td>
                    <td><span class="fl-badge fl-badge--<?php echo $t->is_active ? 'success' : 'warning'; ?>"><?php echo $t->is_active ? esc_html__('Active', 'seo-copilot') : esc_html__('Inactive', 'seo-copilot'); ?></span></td>
                    <td style="text-align:right;white-space:nowrap;">
                        <a class="fl-button fl-button--small" href="<?php echo esc_url($edit); ?>"><?php esc_html_e('Edit', 'seo-copilot'); ?></a>
                        <button type="button" class="fl-button fl-button--small fl-button--danger seocp-tpl-delete" data-id="<?php echo (int) $t->id; ?>" data-name="<?php echo esc_attr($t->name); ?>">
                            <?php esc_html_e('Delete', 'seo-copilot'); ?>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
