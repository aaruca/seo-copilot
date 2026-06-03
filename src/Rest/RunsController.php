<?php

namespace SeoCopilot\Rest;

use SeoCopilot\Runs\BulkRunner;
use SeoCopilot\Runs\RunRepository;

class RunsController extends Controller
{
    private RunRepository $runs;
    private BulkRunner $bulk;

    public function __construct(RunRepository $runs, BulkRunner $bulk)
    {
        $this->runs = $runs;
        $this->bulk = $bulk;
    }

    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/runs', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => [$this, 'permit_run'],
            'args' => [
                'limit'    => ['type' => 'integer', 'default' => 100],
                'batch_id' => ['type' => 'string',  'required' => false],
            ],
        ]);

        register_rest_route($this->namespace, '/runs/totals', [
            'methods'             => 'GET',
            'callback'            => [$this, 'totals'],
            'permission_callback' => [$this, 'permit_run'],
        ]);

        register_rest_route($this->namespace, '/runs', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'truncate'],
            'permission_callback' => [$this, 'permit_manage'],
        ]);

        register_rest_route($this->namespace, '/bulk', [
            'methods'             => 'POST',
            'callback'            => [$this, 'enqueue'],
            'permission_callback' => [$this, 'permit_run'],
            'args' => [
                // Either explicit IDs OR a filter spec (with select_all_matching = true).
                'post_ids'             => ['type' => 'array',   'required' => false],
                'select_all_matching'  => ['type' => 'boolean', 'required' => false],
                'filter'               => ['type' => 'object',  'required' => false],
                'template_id'          => ['type' => 'integer', 'required' => true],
                'fields'               => ['type' => 'array',   'required' => true],
                // 'apply' (auto-apply, default) or 'review' (store proposals for later review)
                'mode'                 => ['type' => 'string',  'required' => false],
                // 'sync' (per-post HTTP, default) or 'batch' (OpenAI Batch API — 50% cheaper, async)
                'dispatch'             => ['type' => 'string',  'required' => false],
            ],
        ]);

        // In-page worker — drains pending jobs synchronously so the UI can
        // advance the queue even when WP-Cron is disabled / low traffic.
        register_rest_route($this->namespace, '/bulk/tick', [
            'methods'             => 'POST',
            'callback'            => [$this, 'tick'],
            'permission_callback' => [$this, 'permit_run'],
            'args' => [
                'batch_id' => ['type' => 'string',  'required' => false],
                'limit'    => ['type' => 'integer', 'required' => false],
            ],
        ]);

        // Recent batches — used by the Bulk Wizard "Recent batches" panel.
        register_rest_route($this->namespace, '/batches', [
            'methods'             => 'GET',
            'callback'            => [$this, 'batches'],
            'permission_callback' => [$this, 'permit_run'],
            'args' => [
                'limit' => ['type' => 'integer', 'required' => false],
            ],
        ]);

        // Cancel pending jobs in a batch. Already-completed jobs keep their
        // applied changes — we never roll back writes.
        register_rest_route($this->namespace, '/bulk/stop', [
            'methods'             => 'POST',
            'callback'            => [$this, 'stop'],
            'permission_callback' => [$this, 'permit_run'],
            'args' => [
                'batch_id' => ['type' => 'string', 'required' => true],
            ],
        ]);
    }

    public function index(\WP_REST_Request $req): \WP_REST_Response
    {
        $limit    = (int) $req->get_param('limit') ?: 100;
        $batch_id = (string) ($req->get_param('batch_id') ?? '');
        $rows     = $this->runs->recent($limit, $batch_id !== '' ? $batch_id : null);
        $progress = $batch_id ? $this->bulk->progress($batch_id) : null;
        return rest_ensure_response(['runs' => $rows, 'progress' => $progress]);
    }

    public function totals(): \WP_REST_Response
    {
        return rest_ensure_response($this->runs->totals());
    }

    public function truncate(): \WP_REST_Response
    {
        $this->runs->truncate();
        return rest_ensure_response(['ok' => true]);
    }

    public function batches(\WP_REST_Request $req): \WP_REST_Response
    {
        $limit = (int) ($req->get_param('limit') ?: 10);
        return rest_ensure_response([
            'batches' => $this->bulk->recent_batches($limit),
        ]);
    }

    public function stop(\WP_REST_Request $req): \WP_REST_Response
    {
        $batch_id = (string) $req->get_param('batch_id');
        if ($batch_id === '') {
            return new \WP_REST_Response(['error' => 'missing_batch_id'], 400);
        }
        $cancelled = $this->bulk->stop_batch($batch_id);
        $progress  = $this->bulk->progress($batch_id);
        return rest_ensure_response([
            'ok'        => true,
            'cancelled' => $cancelled,
            'progress'  => $progress,
        ]);
    }

    public function tick(\WP_REST_Request $req): \WP_REST_Response
    {
        $limit    = (int) ($req->get_param('limit') ?: 3);
        $batch_id = (string) ($req->get_param('batch_id') ?? '');
        $processed = $this->bulk->tick($limit, $batch_id !== '' ? $batch_id : null);
        $progress  = $batch_id !== '' ? $this->bulk->progress($batch_id) : null;
        return rest_ensure_response([
            'processed' => $processed,
            'progress'  => $progress,
        ]);
    }

    public function enqueue(\WP_REST_Request $req): \WP_REST_Response
    {
        $template_id = (int) $req->get_param('template_id');
        $fields      = array_map('sanitize_key', (array) $req->get_param('fields'));
        $mode        = (string) ($req->get_param('mode') ?: 'apply');
        $dispatch    = (string) ($req->get_param('dispatch') ?: 'sync');
        if (!in_array($mode, ['apply', 'review'], true)) {
            $mode = 'apply';
        }
        if (!in_array($dispatch, ['sync', 'batch'], true)) {
            $dispatch = 'sync';
        }
        if (!$template_id || !$fields) {
            return new \WP_REST_Response(['error' => 'missing_template_or_fields'], 400);
        }

        // Filter-mode: enumerate matching IDs server-side. Used by Bulk Wizard's
        // "Select all NN,NNN matching" affordance — avoids shipping a 60k array
        // of IDs over the wire and makes the operation safe for large catalogs.
        if ($req->get_param('select_all_matching')) {
            // Filter-mode is admin-only — we skip per-post edit_post checks for
            // performance and rely on manage_options as a coarser gate.
            if (!current_user_can('manage_options')) {
                return new \WP_REST_Response(['error' => 'select_all_requires_admin'], 403);
            }
            $filter_in = (array) ($req->get_param('filter') ?: []);
            $filter = [
                'post_type' => sanitize_key((string) ($filter_in['post_type'] ?? '')),
                'status'    => sanitize_key((string) ($filter_in['status'] ?? 'publish')) ?: 'publish',
                'q'         => trim((string) ($filter_in['q'] ?? '')),
                'preset'    => sanitize_key((string) ($filter_in['preset'] ?? '')),
            ];
            if ($filter['post_type'] === '') {
                return new \WP_REST_Response(['error' => 'missing_post_type'], 400);
            }
            $result = $this->bulk->enqueue_from_filter($filter, $template_id, $fields, $mode, $dispatch);
            $result['mode']     = $mode;
            $result['dispatch'] = $result['dispatch'] ?? $dispatch;
            return rest_ensure_response($result);
        }

        // Explicit-IDs mode (Smart Optimizer / small bulk picks).
        $post_ids = array_values(array_filter(array_map('intval', (array) $req->get_param('post_ids'))));
        if (!$post_ids) {
            return new \WP_REST_Response(['error' => 'no_post_ids'], 400);
        }
        foreach ($post_ids as $pid) {
            if (!current_user_can('edit_post', $pid)) {
                return new \WP_REST_Response(['error' => 'forbidden_post', 'post_id' => $pid], 403);
            }
        }
        $batch_id = $this->bulk->enqueue($post_ids, $template_id, $fields, $mode, $dispatch);
        return rest_ensure_response([
            'batch_id'  => $batch_id,
            'count'     => count($post_ids),
            'truncated' => false,
            'mode'      => $mode,
            'dispatch'  => $dispatch,
        ]);
    }
}
