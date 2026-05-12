<?php
use SeoCopilot\Templates\Template;

/**
 * @var array<int, string>      $enabled_types
 * @var array<int, Template>    $templates
 */
$tpl_payload = array_map(static function (Template $t) {
    return [
        'id'                    => (int) $t->id,
        'name'                  => $t->name,
        'description'           => $t->description,
        'produces'              => $t->produces,
        'applies_to_post_types' => $t->applies_to_post_types,
    ];
}, $templates);
?>
<div id="seocp-smart" class="seocp-page" data-templates='<?php echo esc_attr(wp_json_encode($tpl_payload)); ?>'>

    <?php seocp_partial('components/stepper', [
        'active' => 0,
        'steps'  => [
            ['key' => 'pick',     'label' => __('Pick a post', 'seo-copilot'),         'hint' => __('Choose what to optimize', 'seo-copilot')],
            ['key' => 'configure','label' => __('Choose template & fields', 'seo-copilot'), 'hint' => __('What can be written', 'seo-copilot')],
            ['key' => 'review',   'label' => __('Review & apply', 'seo-copilot'),       'hint' => __('SERP preview, edit, apply', 'seo-copilot')],
        ],
    ]); ?>

    <!-- ============= STEP 1: pick ============= -->
    <section class="fl-wizard__panel" data-step="0" data-active="true">
        <div class="fl-card">
            <div class="fl-card__title"><?php esc_html_e('Pick the post to optimize', 'seo-copilot'); ?></div>
            <div class="fl-card__subtitle"><?php esc_html_e('Choose a post type, then search by title. The current SEO state is shown for each result so you can spot the gaps.', 'seo-copilot'); ?></div>

            <div class="fl-grid-2">
                <div class="fl-field">
                    <label class="fl-field__label" for="seocp-smart-pt"><?php esc_html_e('Post type', 'seo-copilot'); ?></label>
                    <select id="seocp-smart-pt" class="fl-select">
                        <option value=""><?php esc_html_e('— Choose —', 'seo-copilot'); ?></option>
                        <?php foreach ($enabled_types as $pt): ?>
                            <option value="<?php echo esc_attr($pt); ?>"><?php echo esc_html($pt); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="fl-field__hint"><?php esc_html_e('Only post types you enabled in Settings appear here.', 'seo-copilot'); ?></span>
                </div>
                <div class="fl-field">
                    <label class="fl-field__label" for="seocp-smart-search"><?php esc_html_e('Search by title', 'seo-copilot'); ?></label>
                    <input id="seocp-smart-search" class="fl-input" placeholder="<?php esc_attr_e('Type to filter…', 'seo-copilot'); ?>" />
                </div>
            </div>

            <div id="seocp-smart-results" class="seocp-results"></div>
            <div id="seocp-smart-empty" class="fl-message-bar" hidden>
                <span><?php esc_html_e('Pick a post type to see candidates.', 'seo-copilot'); ?></span>
            </div>
        </div>

        <div class="fl-wizard__footer">
            <span id="seocp-smart-picked" class="fl-muted"></span>
            <span class="fl-spacer"></span>
            <button id="seocp-smart-next-1" type="button" class="fl-button fl-button--primary" disabled>
                <?php esc_html_e('Next: choose template', 'seo-copilot'); ?>
            </button>
        </div>
    </section>

    <!-- ============= STEP 2: configure ============= -->
    <section class="fl-wizard__panel" data-step="1">
        <div class="fl-card">
            <div class="fl-card__title"><?php esc_html_e('Pick a template', 'seo-copilot'); ?></div>
            <div class="fl-card__subtitle"><?php esc_html_e('Templates define which fields the AI is allowed to produce.', 'seo-copilot'); ?></div>
            <div id="seocp-smart-tpl-list" class="fl-stack"></div>
        </div>

        <div class="fl-card">
            <div class="fl-card__title"><?php esc_html_e('Fields to (re)write', 'seo-copilot'); ?></div>
            <div class="fl-card__subtitle"><?php esc_html_e('Only ticked fields are written. Anything unticked stays exactly as it is.', 'seo-copilot'); ?></div>
            <div id="seocp-smart-fields" class="seocp-field-grid"></div>
        </div>

        <div class="fl-wizard__footer">
            <button id="seocp-smart-back-1" type="button" class="fl-button fl-button--subtle"><?php esc_html_e('Back', 'seo-copilot'); ?></button>
            <span class="fl-spacer"></span>
            <span id="seocp-smart-cost" class="fl-muted"></span>
            <button id="seocp-smart-generate" type="button" class="fl-button fl-button--primary" disabled>
                <?php esc_html_e('Generate proposal', 'seo-copilot'); ?>
            </button>
        </div>
    </section>

    <!-- ============= STEP 3: review ============= -->
    <section class="fl-wizard__panel" data-step="2">
        <div id="seocp-smart-status-bar" class="fl-message-bar" hidden></div>

        <div id="seocp-smart-serp-card" class="fl-card" hidden>
            <div class="fl-card__title"><?php esc_html_e('Google preview', 'seo-copilot'); ?></div>
            <div class="fl-card__subtitle"><?php esc_html_e('Live preview of how the search snippet will render.', 'seo-copilot'); ?></div>
            <div id="seocp-smart-serp"></div>
        </div>

        <div id="seocp-smart-output" class="fl-stack-l"></div>

        <div class="fl-wizard__footer">
            <button id="seocp-smart-back-2" type="button" class="fl-button fl-button--subtle"><?php esc_html_e('Back', 'seo-copilot'); ?></button>
            <span class="fl-spacer"></span>
            <button id="seocp-smart-apply" type="button" class="fl-button fl-button--primary" disabled>
                <?php esc_html_e('Apply selected fields', 'seo-copilot'); ?>
            </button>
        </div>
    </section>
</div>
