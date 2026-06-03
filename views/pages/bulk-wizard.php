<?php
use SeoCopilot\Templates\Template;

/**
 * @var array<int, string>   $enabled_types
 * @var array<int, Template> $templates
 */
$tpl_payload = array_map(static function (Template $t) {
    return [
        'id' => (int) $t->id,
        'name' => $t->name,
        'description' => $t->description,
        'produces' => $t->produces,
        'applies_to_post_types' => $t->applies_to_post_types,
    ];
}, $templates);
?>
<div id="seocp-bulk" class="seocp-page" data-templates='<?php echo esc_attr(wp_json_encode($tpl_payload)); ?>'>

    <?php seocp_partial('components/stepper', [
        'active' => 0,
        'steps'  => [
            ['key' => 'filter',   'label' => __('Find candidates', 'seo-copilot'), 'hint' => __('Search & preview', 'seo-copilot')],
            ['key' => 'select',   'label' => __('Select posts', 'seo-copilot'),    'hint' => __('Pick what to run', 'seo-copilot')],
            ['key' => 'configure','label' => __('Template & fields', 'seo-copilot'), 'hint' => __('What to write', 'seo-copilot')],
            ['key' => 'confirm',  'label' => __('Confirm & run', 'seo-copilot'),   'hint' => __('Review estimate', 'seo-copilot')],
        ],
    ]); ?>

    <!-- ============= STEP 1: filter ============= -->
    <section class="fl-wizard__panel" data-step="0" data-active="true">
        <div class="fl-card">
            <div class="fl-card__title"><?php esc_html_e('Find candidate posts', 'seo-copilot'); ?></div>
            <div class="fl-card__subtitle"><?php esc_html_e('Use a preset to find posts that need SEO work, or filter manually. Results are paginated — works on catalogs up to 50,000 products.', 'seo-copilot'); ?></div>

            <div class="fl-grid-3">
                <div class="fl-field">
                    <label class="fl-field__label"><?php esc_html_e('Post type', 'seo-copilot'); ?></label>
                    <select id="seocp-bulk-pt" class="fl-select">
                        <option value=""><?php esc_html_e('— Choose —', 'seo-copilot'); ?></option>
                        <?php foreach ($enabled_types as $pt): ?>
                            <option value="<?php echo esc_attr($pt); ?>"><?php echo esc_html($pt); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fl-field">
                    <label class="fl-field__label"><?php esc_html_e('Status', 'seo-copilot'); ?></label>
                    <select id="seocp-bulk-status" class="fl-select">
                        <option value="publish">publish</option>
                        <option value="draft">draft</option>
                        <option value="pending">pending</option>
                        <option value="any">any</option>
                    </select>
                </div>
                <div class="fl-field">
                    <label class="fl-field__label"><?php esc_html_e('Search by title', 'seo-copilot'); ?></label>
                    <input id="seocp-bulk-q" class="fl-input" placeholder="<?php esc_attr_e('Optional', 'seo-copilot'); ?>" />
                </div>
            </div>

            <div class="fl-field">
                <label class="fl-field__label"><?php esc_html_e('Quick presets', 'seo-copilot'); ?></label>
                <div class="fl-row" role="radiogroup">
                    <label class="fl-choice"><input type="radio" name="seocp-bulk-preset" value="" checked /><span><?php esc_html_e('All', 'seo-copilot'); ?></span></label>
                    <label class="fl-choice"><input type="radio" name="seocp-bulk-preset" value="missing_meta" /><span><?php esc_html_e('Missing meta description', 'seo-copilot'); ?></span></label>
                    <label class="fl-choice"><input type="radio" name="seocp-bulk-preset" value="missing_focus" /><span><?php esc_html_e('Missing focus keyword', 'seo-copilot'); ?></span></label>
                </div>
            </div>

            <div class="fl-row">
                <button id="seocp-bulk-search" type="button" class="fl-button"><?php esc_html_e('Find posts', 'seo-copilot'); ?></button>
                <span id="seocp-bulk-count" class="fl-muted"></span>
            </div>
        </div>

        <div class="fl-wizard__footer">
            <span class="fl-spacer"></span>
            <button id="seocp-bulk-next-1" type="button" class="fl-button fl-button--primary" disabled>
                <?php esc_html_e('Next: select posts', 'seo-copilot'); ?>
            </button>
        </div>
    </section>

    <!-- ============= STEP 2: select ============= -->
    <section class="fl-wizard__panel" data-step="1">
        <div class="fl-card">
            <div class="fl-card__title"><?php esc_html_e('Select posts to optimize', 'seo-copilot'); ?></div>
            <div class="fl-card__subtitle"><?php esc_html_e('Tick individually or use "Select all matching" to queue every post that fits your filter — even across thousands of pages.', 'seo-copilot'); ?></div>

            <!-- Select-all-matching banner -->
            <div id="seocp-bulk-bulk-bar" class="fl-bulk-bar" data-state="page">
                <label class="fl-choice">
                    <input type="checkbox" id="seocp-bulk-allmatch" />
                    <span id="seocp-bulk-allmatch-label"><?php esc_html_e('Select all matching posts', 'seo-copilot'); ?></span>
                </label>
                <span class="fl-spacer"></span>
                <span id="seocp-bulk-selcount" class="fl-muted"></span>
            </div>

            <!-- Pager -->
            <div class="fl-pager" id="seocp-bulk-pager">
                <span class="fl-pager__count" id="seocp-bulk-pager-count"></span>
                <span class="fl-spacer"></span>
                <label><?php esc_html_e('Per page:', 'seo-copilot'); ?>
                    <select id="seocp-bulk-perpage" class="fl-pager__perpage">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </label>
                <span class="fl-pager__nav">
                    <button type="button" class="fl-pager__btn" id="seocp-bulk-pager-first" title="<?php esc_attr_e('First page', 'seo-copilot'); ?>">«</button>
                    <button type="button" class="fl-pager__btn" id="seocp-bulk-pager-prev" title="<?php esc_attr_e('Previous', 'seo-copilot'); ?>">‹</button>
                    <span><?php esc_html_e('Page', 'seo-copilot'); ?>
                        <input type="number" min="1" value="1" id="seocp-bulk-pager-page" class="fl-pager__input" />
                        <?php esc_html_e('of', 'seo-copilot'); ?> <span id="seocp-bulk-pager-total">1</span>
                    </span>
                    <button type="button" class="fl-pager__btn" id="seocp-bulk-pager-next" title="<?php esc_attr_e('Next', 'seo-copilot'); ?>">›</button>
                    <button type="button" class="fl-pager__btn" id="seocp-bulk-pager-last" title="<?php esc_attr_e('Last page', 'seo-copilot'); ?>">»</button>
                </span>
            </div>

            <div class="fl-row" style="margin:8px 0;">
                <label class="fl-choice"><input id="seocp-bulk-all" type="checkbox" /><span><?php esc_html_e('Select all on this page', 'seo-copilot'); ?></span></label>
            </div>

            <div id="seocp-bulk-results" class="seocp-results"></div>
        </div>

        <div class="fl-wizard__footer">
            <button id="seocp-bulk-back-1" type="button" class="fl-button fl-button--subtle"><?php esc_html_e('Back', 'seo-copilot'); ?></button>
            <span class="fl-spacer"></span>
            <button id="seocp-bulk-next-2" type="button" class="fl-button fl-button--primary" disabled>
                <?php esc_html_e('Next: choose template', 'seo-copilot'); ?>
            </button>
        </div>
    </section>

    <!-- ============= STEP 3: configure ============= -->
    <section class="fl-wizard__panel" data-step="2">
        <div class="fl-card">
            <div class="fl-card__title"><?php esc_html_e('Pick a template', 'seo-copilot'); ?></div>
            <div id="seocp-bulk-tpl-list" class="fl-stack"></div>
        </div>

        <div class="fl-card">
            <div class="fl-card__title"><?php esc_html_e('Fields to write', 'seo-copilot'); ?></div>
            <div class="fl-card__subtitle"><?php esc_html_e('Only ticked fields are written across the entire batch. Unticked fields are never touched.', 'seo-copilot'); ?></div>
            <div id="seocp-bulk-fields" class="seocp-field-grid"></div>
        </div>

        <div class="fl-wizard__footer">
            <button id="seocp-bulk-back-2" type="button" class="fl-button fl-button--subtle"><?php esc_html_e('Back', 'seo-copilot'); ?></button>
            <span class="fl-spacer"></span>
            <button id="seocp-bulk-next-3" type="button" class="fl-button fl-button--primary" disabled>
                <?php esc_html_e('Next: review', 'seo-copilot'); ?>
            </button>
        </div>
    </section>

    <!-- ============= STEP 4: confirm ============= -->
    <section class="fl-wizard__panel" data-step="3">
        <div class="fl-card">
            <div class="fl-card__title"><?php esc_html_e('Ready to run', 'seo-copilot'); ?></div>
            <div class="fl-card__subtitle"><?php esc_html_e('Review the estimate, then queue. Jobs run in the background via WP-Cron — you can leave this page.', 'seo-copilot'); ?></div>

            <div class="seocp-cost-card">
                <div class="fl-kpi"><div class="fl-kpi__label"><?php esc_html_e('Posts', 'seo-copilot'); ?></div><div id="seocp-bulk-kpi-posts" class="fl-kpi__value">0</div></div>
                <div class="fl-kpi"><div class="fl-kpi__label"><?php esc_html_e('Fields per post', 'seo-copilot'); ?></div><div id="seocp-bulk-kpi-fields" class="fl-kpi__value">0</div></div>
                <div class="fl-kpi"><div class="fl-kpi__label"><?php esc_html_e('Total AI calls', 'seo-copilot'); ?></div><div id="seocp-bulk-kpi-calls" class="fl-kpi__value">0</div></div>
                <div class="fl-kpi"><div class="fl-kpi__label"><?php esc_html_e('Estimated cost', 'seo-copilot'); ?></div><div id="seocp-bulk-kpi-cost" class="fl-kpi__value">$0.00</div><div class="fl-kpi__sub"><?php esc_html_e('rough USD estimate', 'seo-copilot'); ?></div></div>
            </div>

            <hr class="fl-divider" />

            <div class="fl-field">
                <label class="fl-field__label"><?php esc_html_e('Processing mode', 'seo-copilot'); ?></label>
                <div class="fl-stack">
                    <label class="fl-selectable" data-dispatch-option="sync" aria-selected="true" style="cursor:pointer;">
                        <input type="radio" name="seocp-bulk-dispatch" value="sync" checked style="margin-right:12px;" />
                        <div class="fl-selectable__body">
                            <div class="fl-selectable__title"><?php esc_html_e('Synchronous (default)', 'seo-copilot'); ?></div>
                            <div class="fl-selectable__meta"><?php esc_html_e('Each post calls OpenAI directly. Live progress, immediate writes. Subject to the per-minute rate limit. Best for small batches (under ~500 posts).', 'seo-copilot'); ?></div>
                        </div>
                    </label>
                    <label class="fl-selectable" data-dispatch-option="batch" style="cursor:pointer;">
                        <input type="radio" name="seocp-bulk-dispatch" value="batch" style="margin-right:12px;" />
                        <div class="fl-selectable__body">
                            <div class="fl-selectable__title"><?php esc_html_e('OpenAI Batch API (50% cheaper, recommended for 500+ posts)', 'seo-copilot'); ?></div>
                            <div class="fl-selectable__meta"><?php esc_html_e('All posts submitted to OpenAI\'s Batch API in chunks of 5,000. Half the token price. No per-minute rate limit. Results return within 24h (often much sooner) — you can close this page; we\'ll apply each result as it comes back.', 'seo-copilot'); ?></div>
                        </div>
                    </label>
                </div>
            </div>

            <hr class="fl-divider" />

            <div class="fl-field">
                <label class="fl-field__label"><?php esc_html_e('How should changes be applied?', 'seo-copilot'); ?></label>
                <div class="fl-stack">
                    <label class="fl-selectable" data-mode-option="apply" aria-selected="true" style="cursor:pointer;">
                        <input type="radio" name="seocp-bulk-mode" value="apply" checked style="margin-right:12px;" />
                        <div class="fl-selectable__body">
                            <div class="fl-selectable__title"><?php esc_html_e('Auto-apply (default)', 'seo-copilot'); ?></div>
                            <div class="fl-selectable__meta"><?php esc_html_e('Each post is written immediately as it finishes generating. Fastest. Best for trusted templates and rollouts where you don\'t need to inspect every change.', 'seo-copilot'); ?></div>
                        </div>
                    </label>
                    <label class="fl-selectable" data-mode-option="review" style="cursor:pointer;">
                        <input type="radio" name="seocp-bulk-mode" value="review" style="margin-right:12px;" />
                        <div class="fl-selectable__body">
                            <div class="fl-selectable__title"><?php esc_html_e('Generate for review', 'seo-copilot'); ?></div>
                            <div class="fl-selectable__meta"><?php esc_html_e('Proposals are saved to "Pending Review". Nothing is written until you approve. Best for first-time bulk runs or high-stakes copy.', 'seo-copilot'); ?></div>
                        </div>
                    </label>
                </div>
            </div>

            <div class="fl-message-bar fl-message-bar--warning">
                <strong><?php esc_html_e('Bulk runs auto-apply.', 'seo-copilot'); ?></strong>
                <span><?php esc_html_e('Unlike Smart Optimizer, there is NO separate review step — as each post finishes generating, the new metadata is written immediately. Each post is retried up to 3 times on failure.', 'seo-copilot'); ?></span>
            </div>
            <div class="fl-message-bar">
                <strong><?php esc_html_e('You can leave this page.', 'seo-copilot'); ?></strong>
                <span><?php esc_html_e('Jobs continue in the background via WP-Cron. Come back anytime — completed batches appear in the "Recent batches" card above, and every write lands in Settings → Logs & Diagnostics.', 'seo-copilot'); ?></span>
            </div>
        </div>

        <div id="seocp-bulk-progress-card" class="fl-card" hidden>
            <div class="fl-card__title"><?php esc_html_e('Live progress', 'seo-copilot'); ?></div>
            <div id="seocp-bulk-progress"></div>
        </div>

        <div class="fl-wizard__footer">
            <button id="seocp-bulk-back-3" type="button" class="fl-button fl-button--subtle"><?php esc_html_e('Back', 'seo-copilot'); ?></button>
            <span class="fl-spacer"></span>
            <button id="seocp-bulk-enqueue" type="button" class="fl-button fl-button--primary fl-button--large">
                <?php esc_html_e('Queue batch', 'seo-copilot'); ?>
            </button>
        </div>
    </section>

    <!-- Recent batches: lives at the bottom, collapsed by default. The wizard's
         working state is in JS memory only, so this card is the way to revisit
         past runs after navigating away. Auto-expands when there's an active
         (pending or running) batch so in-flight work isn't hidden. -->
    <div id="seocp-bulk-recent-card" class="fl-collapse" hidden data-collapsed="true" style="margin-top:32px;">
        <button type="button" class="fl-collapse__head" id="seocp-bulk-recent-toggle" aria-expanded="false" aria-controls="seocp-bulk-recent-body-wrap">
            <span class="fl-collapse__chevron" aria-hidden="true">▸</span>
            <span class="fl-collapse__title"><?php esc_html_e('Recent batches', 'seo-copilot'); ?></span>
            <span id="seocp-bulk-recent-count" class="fl-badge"></span>
            <span class="fl-spacer"></span>
            <span class="fl-muted fl-text-200"><?php esc_html_e('Click to expand', 'seo-copilot'); ?></span>
        </button>
        <div class="fl-collapse__body" id="seocp-bulk-recent-body-wrap">
            <div class="fl-row" style="margin-bottom:8px;">
                <span class="fl-muted fl-text-200"><?php esc_html_e('Past 10 batches — click "Show progress" to re-attach the live drawer.', 'seo-copilot'); ?></span>
                <span class="fl-spacer"></span>
                <button type="button" id="seocp-bulk-recent-refresh" class="fl-button fl-button--small fl-button--subtle"><?php esc_html_e('Refresh', 'seo-copilot'); ?></button>
            </div>
            <div id="seocp-bulk-recent-body" class="fl-stack"></div>
        </div>
    </div>
</div>
