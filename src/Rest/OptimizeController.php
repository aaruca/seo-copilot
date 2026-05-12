<?php

namespace SeoCopilot\Rest;

use SeoCopilot\Fields\FieldRegistry;
use SeoCopilot\Runs\Runner;

class OptimizeController extends Controller
{
    private Runner $runner;
    private FieldRegistry $fields;

    public function __construct(Runner $runner, FieldRegistry $fields)
    {
        $this->runner = $runner;
        $this->fields = $fields;
    }

    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/optimize', [
            'methods'             => 'POST',
            'callback'            => [$this, 'optimize'],
            'permission_callback' => [$this, 'permit_post'],
            'args' => [
                'post_id'     => ['required' => true, 'type' => 'integer'],
                'template_id' => ['required' => true, 'type' => 'integer'],
                'fields'      => ['required' => true, 'type' => 'array'],
            ],
        ]);

        register_rest_route($this->namespace, '/apply', [
            'methods'             => 'POST',
            'callback'            => [$this, 'apply'],
            'permission_callback' => [$this, 'permit_post'],
            'args' => [
                'post_id'         => ['required' => true, 'type' => 'integer'],
                'template_id'     => ['required' => false, 'type' => 'integer'],
                'fields_to_write' => ['required' => true, 'type' => 'object'],
            ],
        ]);
    }

    public function permit_post(\WP_REST_Request $req): bool
    {
        if (!$this->permit_run()) {
            return false;
        }
        $post_id = (int) $req->get_param('post_id');
        return $post_id > 0 && current_user_can('edit_post', $post_id);
    }

    public function optimize(\WP_REST_Request $req): \WP_REST_Response
    {
        try {
            $result = $this->runner->generate(
                (int) $req->get_param('post_id'),
                (int) $req->get_param('template_id'),
                array_map('sanitize_key', (array) $req->get_param('fields'))
            );

            $proposal = $result['proposal'];
            $current  = [];
            foreach (array_keys($proposal) as $fid) {
                $field = $this->fields->get($fid);
                $current[$fid] = $field ? $field->read((int) $req->get_param('post_id')) : '';
            }

            return rest_ensure_response([
                'proposal' => $proposal,
                'current'  => $current,
                'run'      => $result['run']->to_array(),
            ]);
        } catch (\Throwable $e) {
            return new \WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function apply(\WP_REST_Request $req): \WP_REST_Response
    {
        try {
            $values = (array) $req->get_param('fields_to_write');
            // Cast every value to string.
            $clean = [];
            foreach ($values as $k => $v) {
                $clean[sanitize_key((string) $k)] = is_scalar($v) ? (string) $v : '';
            }
            $written = $this->runner->apply(
                (int) $req->get_param('post_id'),
                (int) ($req->get_param('template_id') ?: 0) ?: null,
                $clean
            );
            return rest_ensure_response(['ok' => true, 'written' => $written]);
        } catch (\Throwable $e) {
            return new \WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }
}
