<?php

namespace SeoCopilot\Admin\Pages;

use SeoCopilot\Fields\FieldRegistry;
use SeoCopilot\Plugin;
use SeoCopilot\PostTypes\PostTypeRegistry;
use SeoCopilot\Templates\Template;
use SeoCopilot\Templates\TemplateRepository;

class TemplatesPage
{
    public static function render(): void
    {
        $c = Plugin::instance()->container();
        $repo = $c->get(TemplateRepository::class);

        $action = isset($_GET['action']) ? sanitize_key((string) $_GET['action']) : '';
        $id     = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($action === 'edit' || $action === 'new') {
            $tpl = $action === 'edit' && $id ? $repo->find($id) : new Template();
            if (!$tpl) {
                wp_safe_redirect(remove_query_arg(['action', 'id']));
                return;
            }
            seocp_partial('layout/page', [
                'title'   => $tpl->id ? __('Edit Template', 'seo-copilot') : __('New Template', 'seo-copilot'),
                'content' => function () use ($tpl, $c) {
                    seocp_partial('pages/templates-edit', [
                        'template' => $tpl,
                        'fields'   => $c->get(FieldRegistry::class)->union(),
                        'types'    => $c->get(PostTypeRegistry::class)->available(),
                    ]);
                },
            ]);
            return;
        }

        seocp_partial('layout/page', [
            'title'   => __('Templates', 'seo-copilot'),
            'content' => function () use ($repo) {
                seocp_partial('pages/templates-list', [
                    'templates' => $repo->all(),
                ]);
            },
        ]);
    }
}
