<?php

namespace SeoCopilot\Admin\Pages;

use SeoCopilot\Plugin;
use SeoCopilot\PostTypes\PostTypeRegistry;
use SeoCopilot\Templates\TemplateRepository;

class BulkWizardPage
{
    public static function render(): void
    {
        $c = Plugin::instance()->container();
        seocp_partial('layout/page', [
            'title'   => __('Bulk Wizard', 'seo-copilot'),
            'content' => function () use ($c) {
                seocp_partial('pages/bulk-wizard', [
                    'enabled_types' => $c->get(PostTypeRegistry::class)->enabled(),
                    'templates'     => $c->get(TemplateRepository::class)->all(true),
                ]);
            },
        ]);
    }
}
