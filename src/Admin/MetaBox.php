<?php

namespace SeoCopilot\Admin;

use SeoCopilot\Capabilities\Capabilities;
use SeoCopilot\Fields\FieldRegistry;
use SeoCopilot\PostTypes\PostTypeRegistry;
use SeoCopilot\Templates\TemplateRepository;

class MetaBox
{
    private PostTypeRegistry $types;
    private FieldRegistry $fields;
    private TemplateRepository $templates;

    public function __construct(PostTypeRegistry $types, FieldRegistry $fields, TemplateRepository $templates)
    {
        $this->types     = $types;
        $this->fields    = $fields;
        $this->templates = $templates;
    }

    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'add']);
    }

    public function add(): void
    {
        if (!Capabilities::user_can_run()) {
            return;
        }
        foreach ($this->types->enabled() as $pt) {
            add_meta_box(
                'seocp-metabox',
                __('SEO Copilot', 'seo-copilot'),
                [$this, 'render'],
                $pt,
                'side',
                'high'
            );
        }
    }

    public function render(\WP_Post $post): void
    {
        $tpls = $this->templates->for_post_type($post->post_type);
        $defaults = $this->types->field_defaults($post->post_type);
        $grouped = $this->fields->for_post_type($post->post_type);

        seocp_partial('metabox/post-metabox', [
            'post'      => $post,
            'templates' => $tpls,
            'defaults'  => $defaults,
            'grouped'   => $grouped,
        ]);
    }
}
