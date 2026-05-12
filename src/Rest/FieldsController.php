<?php

namespace SeoCopilot\Rest;

use SeoCopilot\Fields\FieldRegistry;
use SeoCopilot\PostTypes\PostTypeRegistry;

class FieldsController extends Controller
{
    private FieldRegistry $fields;
    private PostTypeRegistry $types;

    public function __construct(FieldRegistry $fields, PostTypeRegistry $types)
    {
        $this->fields = $fields;
        $this->types  = $types;
    }

    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/fields/(?P<post_type>[a-z0-9_\-]+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'show'],
            'permission_callback' => [$this, 'permit_run'],
            'args' => [
                'post_type' => ['type' => 'string'],
                'post_id'   => ['type' => 'integer', 'required' => false],
            ],
        ]);

        register_rest_route($this->namespace, '/fields/(?P<post_type>[a-z0-9_\-]+)/defaults', [
            'methods'             => 'POST',
            'callback'            => [$this, 'update_defaults'],
            'permission_callback' => [$this, 'permit_manage'],
            'args' => [
                'post_type' => ['type' => 'string'],
                'fields'    => ['type' => 'array', 'required' => true],
            ],
        ]);

        register_rest_route($this->namespace, '/fields-union', [
            'methods'             => 'GET',
            'callback'            => [$this, 'union'],
            'permission_callback' => [$this, 'permit_edit_templates'],
        ]);
    }

    public function show(\WP_REST_Request $req): \WP_REST_Response
    {
        $post_type = sanitize_key((string) $req['post_type']);
        $post_id   = (int) ($req->get_param('post_id') ?? 0);

        $grouped = $this->fields->for_post_type($post_type);
        $defaults = $this->types->field_defaults($post_type);

        $out = [];
        foreach ($grouped as $group => $fields) {
            $items = [];
            foreach ($fields as $f) {
                $current = $post_id ? $f->read($post_id) : '';
                $items[] = array_merge($f->to_array(), [
                    'current'    => $current,
                    'is_default' => in_array($f->id, $defaults, true),
                ]);
            }
            $out[] = ['group' => $group, 'fields' => $items];
        }
        return rest_ensure_response(['groups' => $out, 'defaults' => $defaults]);
    }

    public function update_defaults(\WP_REST_Request $req): \WP_REST_Response
    {
        $post_type = sanitize_key((string) $req['post_type']);
        $fields    = array_map('sanitize_key', (array) $req->get_param('fields'));
        $this->types->set_field_defaults($post_type, $fields);
        return rest_ensure_response(['ok' => true, 'defaults' => $this->types->field_defaults($post_type)]);
    }

    public function union(): \WP_REST_Response
    {
        $items = [];
        foreach ($this->fields->union() as $f) {
            $items[] = $f->to_array() + ['applies_to' => $f->applies_to];
        }
        return rest_ensure_response(['fields' => $items]);
    }
}
