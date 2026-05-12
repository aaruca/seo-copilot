<?php

namespace SeoCopilot\Rest;

use SeoCopilot\PostTypes\PostTypeRegistry;

class PostTypesController extends Controller
{
    private PostTypeRegistry $registry;

    public function __construct(PostTypeRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/post-types', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => [$this, 'permit_run'],
        ]);

        register_rest_route($this->namespace, '/post-types', [
            'methods'             => 'POST',
            'callback'            => [$this, 'update'],
            'permission_callback' => [$this, 'permit_manage'],
            'args' => [
                'enabled' => ['required' => true, 'type' => 'array'],
            ],
        ]);
    }

    public function index(): \WP_REST_Response
    {
        $available = $this->registry->available();
        $enabled   = $this->registry->enabled();
        $items = [];
        foreach ($available as $slug => $label) {
            $items[] = [
                'slug'    => $slug,
                'label'   => $label,
                'enabled' => in_array($slug, $enabled, true),
            ];
        }
        return rest_ensure_response(['post_types' => $items]);
    }

    public function update(\WP_REST_Request $req): \WP_REST_Response
    {
        $enabled = (array) $req->get_param('enabled');
        $this->registry->set_enabled(array_map('sanitize_key', $enabled));
        return rest_ensure_response(['ok' => true, 'enabled' => $this->registry->enabled()]);
    }
}
