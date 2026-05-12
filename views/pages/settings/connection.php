<?php
/**
 * @var array $settings
 * @var array $models
 */
$key   = (string) ($settings['openai_api_key'] ?? '');
$model = (string) ($settings['openai_model'] ?? 'gpt-4.1-mini');
$rate  = (int)    ($settings['rate_per_min'] ?? 30);
$br    = !empty($settings['enable_bricks']);
$cap   = (int)    ($settings['bricks_char_cap'] ?? 8000);
$biz   = (string) ($settings['business_name'] ?? '');
$city  = (string) ($settings['geo_city'] ?? '');
$reg   = (string) ($settings['geo_region'] ?? '');
$ctry  = (string) ($settings['geo_country'] ?? '');
$svc   = (string) ($settings['geo_service_area'] ?? '');
?>
<form method="post" action="">
    <?php wp_nonce_field('seocp_settings'); ?>
    <input type="hidden" name="seocp_save_connection" value="1" />

    <?php seocp_partial('components/card', [
        'title' => __('OpenAI', 'seo-copilot'),
        'body'  => function () use ($key, $model, $rate, $models) {
            ?>
            <div class="fl-grid-2">
                <div class="fl-field">
                    <label class="fl-field__label" for="seocp-key"><?php esc_html_e('API key', 'seo-copilot'); ?></label>
                    <input class="fl-input" type="password" id="seocp-key" name="openai_api_key" autocomplete="new-password"
                           value="<?php echo esc_attr($key); ?>" placeholder="sk-..." />
                    <span class="fl-field__hint"><?php esc_html_e('Stored in wp_options. Use a project-scoped key with usage limits.', 'seo-copilot'); ?></span>
                </div>
                <div class="fl-field">
                    <label class="fl-field__label" for="seocp-model"><?php esc_html_e('Default model', 'seo-copilot'); ?></label>
                    <select class="fl-select" id="seocp-model" name="openai_model">
                        <?php foreach ($models as $m): ?>
                            <option value="<?php echo esc_attr($m); ?>" <?php selected($model, $m); ?>><?php echo esc_html($m); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fl-field">
                    <label class="fl-field__label" for="seocp-rate"><?php esc_html_e('Requests per minute (per user)', 'seo-copilot'); ?></label>
                    <input class="fl-input" type="number" min="1" max="600" id="seocp-rate" name="rate_per_min" value="<?php echo esc_attr((string) $rate); ?>" />
                </div>
            </div>
            <?php
        },
    ]); ?>

    <div class="fl-divider"></div>

    <?php seocp_partial('components/card', [
        'title' => __('Local SEO defaults', 'seo-copilot'),
        'subtitle' => __('Used as fallback geo signals when the source post has no location of its own. Templates never invent a location — leave blank if you don\'t serve a specific area.', 'seo-copilot'),
        'body'  => function () use ($biz, $city, $reg, $ctry, $svc) {
            ?>
            <div class="fl-grid-2">
                <div class="fl-field">
                    <label class="fl-field__label" for="seocp-biz"><?php esc_html_e('Business / brand name', 'seo-copilot'); ?></label>
                    <input class="fl-input" id="seocp-biz" name="business_name" value="<?php echo esc_attr($biz); ?>" placeholder="<?php esc_attr_e('e.g. Aurora Coffee Roasters', 'seo-copilot'); ?>" />
                    <span class="fl-field__hint"><?php esc_html_e('Falls back to your WordPress site title if empty.', 'seo-copilot'); ?></span>
                </div>
                <div class="fl-field">
                    <label class="fl-field__label" for="seocp-city"><?php esc_html_e('Primary city', 'seo-copilot'); ?></label>
                    <input class="fl-input" id="seocp-city" name="geo_city" value="<?php echo esc_attr($city); ?>" placeholder="<?php esc_attr_e('e.g. Austin', 'seo-copilot'); ?>" />
                </div>
                <div class="fl-field">
                    <label class="fl-field__label" for="seocp-region"><?php esc_html_e('Region / state', 'seo-copilot'); ?></label>
                    <input class="fl-input" id="seocp-region" name="geo_region" value="<?php echo esc_attr($reg); ?>" placeholder="<?php esc_attr_e('e.g. TX', 'seo-copilot'); ?>" />
                </div>
                <div class="fl-field">
                    <label class="fl-field__label" for="seocp-country"><?php esc_html_e('Country', 'seo-copilot'); ?></label>
                    <input class="fl-input" id="seocp-country" name="geo_country" value="<?php echo esc_attr($ctry); ?>" placeholder="<?php esc_attr_e('e.g. United States', 'seo-copilot'); ?>" />
                </div>
                <div class="fl-field" style="grid-column:1/-1;">
                    <label class="fl-field__label" for="seocp-svc"><?php esc_html_e('Service area (free-form)', 'seo-copilot'); ?></label>
                    <input class="fl-input" id="seocp-svc" name="geo_service_area" value="<?php echo esc_attr($svc); ?>" placeholder="<?php esc_attr_e('e.g. Greater Austin metro, Travis & Williamson counties', 'seo-copilot'); ?>" />
                    <span class="fl-field__hint"><?php esc_html_e('Optional. Useful for "near me" / service-area phrasing in metadata.', 'seo-copilot'); ?></span>
                </div>
            </div>
            <?php
        },
    ]); ?>

    <div class="fl-divider"></div>

    <?php seocp_partial('components/card', [
        'title' => __('Bricks Builder', 'seo-copilot'),
        'subtitle' => __('When enabled, Bricks page content is read into prompt context as {{builder_plain_text}}.', 'seo-copilot'),
        'body'  => function () use ($br, $cap) {
            ?>
            <label class="fl-switch">
                <input type="checkbox" name="enable_bricks" value="1" <?php checked($br); ?> />
                <span class="fl-switch__track"><span class="fl-switch__thumb"></span></span>
                <span><?php esc_html_e('Read Bricks page content', 'seo-copilot'); ?></span>
            </label>
            <div class="fl-field" style="margin-top:12px;max-width:280px;">
                <label class="fl-field__label" for="seocp-cap"><?php esc_html_e('Builder text cap (characters)', 'seo-copilot'); ?></label>
                <input class="fl-input" type="number" min="500" max="64000" step="500" id="seocp-cap" name="bricks_char_cap" value="<?php echo esc_attr((string) $cap); ?>" />
                <span class="fl-field__hint"><?php esc_html_e('Dynamic-data tags, query loops, and template-included elements are not resolved.', 'seo-copilot'); ?></span>
            </div>
            <?php
        },
    ]); ?>

    <p style="margin-top:20px;">
        <button type="submit" class="fl-button fl-button--primary"><?php esc_html_e('Save settings', 'seo-copilot'); ?></button>
    </p>
</form>
