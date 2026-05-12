<?php

namespace SeoCopilot\Rest;

use SeoCopilot\Fields\FieldRegistry;
use SeoCopilot\Runs\Runner;
use SeoCopilot\Runs\SegmentRepository;

class SegmentsController extends Controller
{
    private SegmentRepository $segments;
    private Runner $runner;
    private FieldRegistry $fields;

    public function __construct(SegmentRepository $segments, Runner $runner, FieldRegistry $fields)
    {
        $this->segments = $segments;
        $this->runner   = $runner;
        $this->fields   = $fields;
    }

    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/segments', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => [$this, 'permit_run'],
            'args' => [
                'batch_id'  => ['type' => 'string',  'required' => false],
                'post_type' => ['type' => 'string',  'required' => false],
                'limit'     => ['type' => 'integer', 'required' => false],
                'offset'    => ['type' => 'integer', 'required' => false],
            ],
        ]);

        register_rest_route($this->namespace, '/segments/pending-batches', [
            'methods'             => 'GET',
            'callback'            => [$this, 'pending_batches'],
            'permission_callback' => [$this, 'permit_run'],
        ]);

        register_rest_route($this->namespace, '/segments/apply', [
            'methods'             => 'POST',
            'callback'            => [$this, 'apply'],
            'permission_callback' => [$this, 'permit_run'],
            'args' => [
                // Either explicit segment IDs (with optional `value` overrides)
                // OR `batch_id` to apply every pending segment in that batch.
                'items'    => ['type' => 'array',  'required' => false],
                'batch_id' => ['type' => 'string', 'required' => false],
            ],
        ]);

        register_rest_route($this->namespace, '/segments/reject', [
            'methods'             => 'POST',
            'callback'            => [$this, 'reject'],
            'permission_callback' => [$this, 'permit_run'],
            'args' => [
                'ids'      => ['type' => 'array',  'required' => false],
                'batch_id' => ['type' => 'string', 'required' => false],
            ],
        ]);
    }

    public function index(\WP_REST_Request $req): \WP_REST_Response
    {
        $batch_id  = (string) ($req->get_param('batch_id') ?? '');
        $post_type = (string) ($req->get_param('post_type') ?? '');
        $limit     = (int) ($req->get_param('limit') ?: 200);
        $offset    = (int) ($req->get_param('offset') ?: 0);

        $rows = $this->segments->list_pending(
            $limit,
            $offset,
            $batch_id !== '' ? $batch_id : null,
            $post_type !== '' ? $post_type : null
        );

        // Group by post_id and enrich with current values + post info.
        $grouped = [];
        foreach ($rows as $row) {
            $pid = (int) $row['post_id'];
            if (!isset($grouped[$pid])) {
                $post = get_post($pid);
                $grouped[$pid] = [
                    'post_id'     => $pid,
                    'title'       => $post ? (string) $post->post_title : ('#' . $pid),
                    'permalink'   => $post ? (string) get_permalink($post) : '',
                    'edit_url'    => $post ? (string) get_edit_post_link($pid, '') : '',
                    'thumb'       => (string) (get_the_post_thumbnail_url($pid, 'thumbnail') ?: ''),
                    'post_type'   => $post ? (string) $post->post_type : '',
                    'template_id' => (int) ($row['template_id'] ?? 0),
                    'batch_id'    => (string) ($row['batch_id'] ?? ''),
                    'segments'    => [],
                ];
            }
            $field = $this->fields->get((string) $row['field_id']);
            $grouped[$pid]['segments'][] = [
                'id'              => (int) $row['id'],
                'field_id'        => (string) $row['field_id'],
                'label'           => $field ? $field->label : (string) $row['field_id'],
                'group'           => $field ? $field->group : '',
                'max_length'      => $field ? $field->max_length : 0,
                'current_value'   => $field ? $field->read($pid) : '',
                'generated_value' => (string) $row['generated_value'],
                'generated_at'    => (string) $row['generated_at'],
            ];
        }

        return rest_ensure_response([
            'posts' => array_values($grouped),
            'total' => $this->segments->pending_count($batch_id !== '' ? $batch_id : null),
        ]);
    }

    public function pending_batches(): \WP_REST_Response
    {
        return rest_ensure_response(['batches' => $this->segments->pending_batches()]);
    }

    /**
     * Apply selected segments. Each item: { id: int, value?: string }.
     * If `value` is supplied (user edited the proposal), that's what gets written.
     * Otherwise the stored `generated_value` is used.
     *
     * Alternative shape: { batch_id: string } applies every pending segment in that batch.
     */
    public function apply(\WP_REST_Request $req): \WP_REST_Response
    {
        $items    = (array) ($req->get_param('items') ?: []);
        $batch_id = (string) ($req->get_param('batch_id') ?? '');

        // Resolve segment rows + value overrides.
        $rows = [];
        $value_overrides = [];
        if ($items) {
            $ids = [];
            foreach ($items as $item) {
                if (is_array($item) && isset($item['id'])) {
                    $id = (int) $item['id'];
                    if ($id > 0) {
                        $ids[] = $id;
                        if (isset($item['value']) && is_string($item['value'])) {
                            $value_overrides[$id] = $item['value'];
                        }
                    }
                }
            }
            $rows = $this->segments->fetch_many($ids);
        } elseif ($batch_id !== '') {
            // Apply everything pending in the batch.
            $rows = $this->segments->list_pending(5000, 0, $batch_id);
        } else {
            return new \WP_REST_Response(['error' => 'no_items_or_batch_id'], 400);
        }

        if (!$rows) {
            return rest_ensure_response(['ok' => true, 'applied' => 0, 'skipped' => 0]);
        }

        // Group rows by post — Runner::apply takes one post at a time.
        $by_post = [];
        foreach ($rows as $row) {
            if ((int) $row['approved'] !== 0) continue; // already applied or rejected
            $by_post[(int) $row['post_id']][] = $row;
        }

        $applied_count = 0;
        $skipped_count = 0;
        foreach ($by_post as $post_id => $segs) {
            if (!current_user_can('edit_post', $post_id)) {
                $skipped_count += count($segs);
                continue;
            }
            $values = [];
            $template_id = 0;
            $segment_ids_for_post = [];
            foreach ($segs as $row) {
                $sid   = (int) $row['id'];
                $fid   = (string) $row['field_id'];
                $value = array_key_exists($sid, $value_overrides)
                    ? (string) $value_overrides[$sid]
                    : (string) $row['generated_value'];
                $values[$fid] = $value;
                $template_id  = (int) $row['template_id'];
                $segment_ids_for_post[] = $sid;
            }
            if (!$values) continue;
            try {
                $written = $this->runner->apply($post_id, $template_id ?: null, $values);
                foreach ($segment_ids_for_post as $sid) {
                    // Mark applied for any segment whose field_id ended up written.
                    $row = null;
                    foreach ($segs as $r) { if ((int) $r['id'] === $sid) { $row = $r; break; } }
                    if ($row && in_array((string) $row['field_id'], $written, true)) {
                        $this->segments->mark_applied($sid);
                        $applied_count++;
                    } else {
                        // Field didn't apply (post-type mismatch, guard, etc.) — leave as pending.
                        $skipped_count++;
                    }
                }
            } catch (\Throwable $e) {
                $skipped_count += count($segs);
            }
        }

        return rest_ensure_response([
            'ok'       => true,
            'applied'  => $applied_count,
            'skipped'  => $skipped_count,
        ]);
    }

    public function reject(\WP_REST_Request $req): \WP_REST_Response
    {
        $ids      = array_values(array_filter(array_map('intval', (array) $req->get_param('ids'))));
        $batch_id = (string) ($req->get_param('batch_id') ?? '');

        $count = 0;
        if ($ids) {
            foreach ($ids as $id) {
                if ($this->segments->mark_rejected((int) $id)) $count++;
            }
        } elseif ($batch_id !== '') {
            $count = $this->segments->reject_batch($batch_id);
        } else {
            return new \WP_REST_Response(['error' => 'no_ids_or_batch_id'], 400);
        }

        return rest_ensure_response(['ok' => true, 'rejected' => $count]);
    }
}
