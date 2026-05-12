<?php

namespace SeoCopilot\Admin\Pages;

use SeoCopilot\Plugin;
use SeoCopilot\PostTypes\PostTypeRegistry;
use SeoCopilot\Runs\RunRepository;

class DashboardPage
{
    public static function render(): void
    {
        $c = Plugin::instance()->container();
        $totals = $c->get(RunRepository::class)->totals();
        $recent = $c->get(RunRepository::class)->recent(15);
        $types  = $c->get(PostTypeRegistry::class);

        seocp_partial('layout/page', [
            'title'   => __('SEO Copilot — Dashboard', 'seo-copilot'),
            'content' => function () use ($totals, $recent, $types) {
                seocp_partial('pages/dashboard', [
                    'totals' => $totals,
                    'recent' => $recent,
                    'enabled_types' => $types->enabled(),
                ]);
            },
        ]);
    }
}
