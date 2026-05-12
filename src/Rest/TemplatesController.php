<?php

namespace SeoCopilot\Rest;

use SeoCopilot\Templates\DefaultTemplates;
use SeoCopilot\Templates\Template;
use SeoCopilot\Templates\TemplateRepository;

class TemplatesController extends Controller
{
    private TemplateRepository $repo;

    public function __construct(TemplateRepository $repo)
    {
        $this->repo = $repo;
    }

    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/templates', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'index'],
                'permission_callback' => [$this, 'permit_run'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'create'],
                'permission_callback' => [$this, 'permit_edit_templates'],
            ],
        ]);
        register_rest_route($this->namespace, '/templates/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'show'],
                'permission_callback' => [$this, 'permit_run'],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'update'],
                'permission_callback' => [$this, 'permit_edit_templates'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'destroy'],
                'permission_callback' => [$this, 'permit_edit_templates'],
            ],
        ]);

        register_rest_route($this->namespace, '/templates/restore-defaults', [
            'methods'             => 'POST',
            'callback'            => [$this, 'restore_defaults'],
            'permission_callback' => [$this, 'permit_edit_templates'],
        ]);
    }

    public function index(\WP_REST_Request $req): \WP_REST_Response
    {
        $pt = sanitize_key((string) $req->get_param('post_type'));
        $items = $pt ? $this->repo->for_post_type($pt) : $this->repo->all();
        return rest_ensure_response([
            'templates' => array_map(static fn(Template $t) => $t->to_array(), $items),
        ]);
    }

    public function show(\WP_REST_Request $req): \WP_REST_Response
    {
        $tpl = $this->repo->find((int) $req['id']);
        if (!$tpl) {
            return new \WP_REST_Response(['error' => 'not_found'], 404);
        }
        return rest_ensure_response($tpl->to_array());
    }

    public function create(\WP_REST_Request $req): \WP_REST_Response
    {
        $tpl = new Template($this->normalize($req->get_json_params() ?: $req->get_params()));
        if ($tpl->slug === '') {
            $tpl->slug = sanitize_title($tpl->name) ?: 'template-' . wp_generate_uuid4();
        }
        $tpl->id = null;
        $saved = $this->repo->save($tpl);
        return rest_ensure_response($saved->to_array());
    }

    public function update(\WP_REST_Request $req): \WP_REST_Response
    {
        $existing = $this->repo->find((int) $req['id']);
        if (!$existing) {
            return new \WP_REST_Response(['error' => 'not_found'], 404);
        }
        $merged = array_merge($existing->to_array(), $this->normalize($req->get_json_params() ?: $req->get_params()));
        $merged['id'] = $existing->id;
        $saved = $this->repo->save(new Template($merged));
        return rest_ensure_response($saved->to_array());
    }

    public function destroy(\WP_REST_Request $req): \WP_REST_Response
    {
        $tpl = $this->repo->find((int) $req['id']);
        if (!$tpl) {
            return new \WP_REST_Response(['error' => 'not_found'], 404);
        }
        // If this slug is one of our seeded defaults, remember it as deleted so
        // the boot-time re-seeder doesn't resurrect it on the next version bump.
        $defaults = new DefaultTemplates($this->repo);
        if ($defaults->is_default_slug($tpl->slug)) {
            DefaultTemplates::mark_deleted($tpl->slug);
        }
        $this->repo->delete($tpl->id);
        return rest_ensure_response(['ok' => true]);
    }

    public function restore_defaults(): \WP_REST_Response
    {
        $defaults = new DefaultTemplates($this->repo);
        $added = $defaults->restore();
        return rest_ensure_response(['ok' => true, 'added' => $added]);
    }

    private function normalize(array $params): array
    {
        $out = [];
        foreach (['slug', 'name', 'description', 'system_prompt', 'user_template', 'json_schema'] as $k) {
            if (isset($params[$k])) {
                $out[$k] = is_string($params[$k]) ? wp_kses_post($params[$k]) : '';
            }
        }
        if (isset($params['name'])) {
            $out['name'] = sanitize_text_field((string) $params['name']);
        }
        if (isset($params['slug'])) {
            $out['slug'] = sanitize_title((string) $params['slug']);
        }
        if (isset($params['produces'])) {
            $out['produces'] = array_values(array_map('sanitize_key', (array) $params['produces']));
        }
        if (isset($params['applies_to_post_types'])) {
            $out['applies_to_post_types'] = array_values(array_map('sanitize_key', (array) $params['applies_to_post_types']));
        }
        foreach (['is_default', 'is_active'] as $k) {
            if (isset($params[$k])) {
                $out[$k] = (bool) $params[$k];
            }
        }
        return $out;
    }
}
