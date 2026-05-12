<?php
/**
 * @var int   $pending_count
 * @var array $pending_batches
 */
?>
<div id="seocp-review" class="seocp-page" data-pending-count="<?php echo (int) $pending_count; ?>">

    <div class="fl-card">
        <div class="fl-row" style="margin-bottom:8px;">
            <h2 class="fl-card__title" style="margin:0;flex:1;"><?php esc_html_e('Generated proposals waiting for your approval', 'seo-copilot'); ?></h2>
            <span class="fl-badge fl-badge--info" id="seocp-review-pending-count">
                <?php echo number_format_i18n((int) $pending_count); ?> <?php esc_html_e('pending', 'seo-copilot'); ?>
            </span>
        </div>
        <div class="fl-card__subtitle">
            <?php esc_html_e('These come from Bulk Wizard runs in "Generate for review" mode. Edit the proposed values, tick what you want to apply, then click Apply.', 'seo-copilot'); ?>
        </div>

        <?php if (!$pending_count): ?>
            <div class="fl-message-bar">
                <strong><?php esc_html_e('Nothing to review.', 'seo-copilot'); ?></strong>
                <span><?php esc_html_e('Run a Bulk Wizard batch in "Generate for review" mode and proposals will land here.', 'seo-copilot'); ?></span>
            </div>
        <?php else: ?>
            <div class="fl-grid-2" style="margin-top:12px;">
                <div class="fl-field">
                    <label class="fl-field__label" for="seocp-review-batch"><?php esc_html_e('Filter by batch', 'seo-copilot'); ?></label>
                    <select id="seocp-review-batch" class="fl-select">
                        <option value=""><?php esc_html_e('All batches', 'seo-copilot'); ?></option>
                        <?php foreach ($pending_batches as $b): ?>
                            <option value="<?php echo esc_attr($b['batch_id']); ?>">
                                <?php echo esc_html(substr((string) $b['batch_id'], 0, 8) . ' • ' . $b['count'] . ' pending • ' . $b['oldest']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fl-field">
                    <label class="fl-field__label" for="seocp-review-pertype"><?php esc_html_e('Per page (posts)', 'seo-copilot'); ?></label>
                    <select id="seocp-review-pertype" class="fl-select">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                    </select>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($pending_count): ?>
        <div class="fl-card">
            <div class="fl-row" id="seocp-review-bulkbar">
                <button type="button" class="fl-button fl-button--primary" id="seocp-review-apply-visible">
                    <?php esc_html_e('Apply all visible', 'seo-copilot'); ?>
                </button>
                <button type="button" class="fl-button" id="seocp-review-apply-batch">
                    <?php esc_html_e('Apply entire batch', 'seo-copilot'); ?>
                </button>
                <span class="fl-spacer"></span>
                <button type="button" class="fl-button fl-button--danger" id="seocp-review-reject-batch">
                    <?php esc_html_e('Reject entire batch', 'seo-copilot'); ?>
                </button>
            </div>
        </div>

        <div id="seocp-review-status" class="fl-message-bar" hidden></div>
        <div id="seocp-review-list" class="fl-stack-l"></div>

        <div class="fl-card">
            <div class="fl-row">
                <button type="button" class="fl-button" id="seocp-review-prev"><?php esc_html_e('← Previous', 'seo-copilot'); ?></button>
                <span class="fl-spacer"></span>
                <span id="seocp-review-pageinfo" class="fl-muted"></span>
                <span class="fl-spacer"></span>
                <button type="button" class="fl-button" id="seocp-review-next"><?php esc_html_e('Next →', 'seo-copilot'); ?></button>
            </div>
        </div>
    <?php endif; ?>
</div>
