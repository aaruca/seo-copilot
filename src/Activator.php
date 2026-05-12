<?php

namespace SeoCopilot;

use SeoCopilot\Capabilities\Capabilities;
use SeoCopilot\Database\Schema;
use SeoCopilot\Templates\DefaultTemplates;
use SeoCopilot\Templates\TemplateRepository;

class Activator
{
    public static function activate(): void
    {
        Schema::install();
        Capabilities::ensure(true);

        // Seed defaults; stamp with current plugin version so upgrades re-seed via Plugin::boot().
        if (get_option('seocp_templates_seeded') !== SEOCP_VERSION) {
            (new DefaultTemplates(new TemplateRepository()))->seed();
            update_option('seocp_templates_seeded', SEOCP_VERSION);
        }

        if (false === get_option('seocp_settings')) {
            update_option('seocp_settings', [
                'openai_api_key' => '',
                'openai_model'   => 'gpt-4.1-mini',
                'rate_per_min'   => 30,
                'enable_bricks'  => 1,
                'bricks_char_cap'=> 8000,
                'geo_city'       => '',
                'geo_region'     => '',
                'geo_country'    => '',
                'geo_service_area'=> '',
                'business_name'  => '',
            ]);
        }

        if (false === get_option('seocp_enabled_post_types')) {
            $defaults = ['post', 'page'];
            if (post_type_exists('product')) {
                $defaults[] = 'product';
            }
            update_option('seocp_enabled_post_types', $defaults);
        }

        if (!wp_next_scheduled('seocp_run_bulk_batch')) {
            wp_schedule_event(time() + 60, 'seocp_minute', 'seocp_run_bulk_batch');
        }
    }
}
