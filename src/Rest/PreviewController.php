<?php

namespace SeoCopilot\Rest;

use SeoCopilot\Providers\PromptAssembler;
use SeoCopilot\Templates\TemplateRepository;

class PreviewController extends Controller
{
    private PromptAssembler $assembler;
    private TemplateRepository $repo;

    public function __construct(PromptAssembler $assembler, TemplateRepository $repo)
    {
        $this->assembler = $assembler;
        $this->repo      = $repo;
    }

    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/preview', [
            'methods'             => 'POST',
            'callback'            => [$this, 'preview'],
            'permission_callback' => [$this, 'permit_run'],
            'args' => [
                'post_id'     => ['required' => true, 'type' => 'integer'],
                'template_id' => ['required' => true, 'type' => 'integer'],
                'fields'      => ['required' => true, 'type' => 'array'],
            ],
        ]);
    }

    public function preview(\WP_REST_Request $req): \WP_REST_Response
    {
        $post_id = (int) $req->get_param('post_id');
        if (!current_user_can('edit_post', $post_id)) {
            return new \WP_REST_Response(['error' => 'forbidden'], 403);
        }
        $tpl = $this->repo->find((int) $req->get_param('template_id'));
        if (!$tpl) {
            return new \WP_REST_Response(['error' => 'template_not_found'], 404);
        }
        $fields = array_values(array_map('sanitize_key', (array) $req->get_param('fields')));
        $assembled = $this->assembler->assemble($tpl, $post_id, $fields);

        // Naive token estimate: ~4 chars/token.
        $chars = strlen($assembled['system']) + strlen($assembled['user']);
        $est   = (int) ceil($chars / 4);

        return rest_ensure_response([
            'system'           => $assembled['system'],
            'user'             => $assembled['user'],
            'placeholders'     => $assembled['placeholders'],
            'estimated_tokens' => $est,
        ]);
    }
}
