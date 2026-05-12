<?php

namespace SeoCopilot\Rest;

use SeoCopilot\Fields\FieldRegistry;
use SeoCopilot\PostTypes\PostTypeRegistry;

class PostsController extends Controller
{
    private PostTypeRegistry $types;
    private FieldRegistry $fields;

    public function __construct(PostTypeRegistry $types, FieldRegistry $fields)
    {
        $this->types  = $types;
        $this->fields = $fields;
    }

    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/posts', [
            'methods'             => 'GET',
            'callback'            => [$this, 'search'],
            'permission_callback' => [$this, 'permit_run'],
            'args' => [
                'post_type' => ['type' => 'string',  'required' => true],
                'q'         => ['type' => 'string',  'required' => false],
                'status'    => ['type' => 'string',  'required' => false],
                'preset'    => ['type' => 'string',  'required' => false],
                'page'      => ['type' => 'integer', 'required' => false],
                'per_page'  => ['type' => 'integer', 'required' => false],
                // Legacy single-shot mode (Smart Optimizer post picker).
                'limit'     => ['type' => 'integer', 'required' => false],
            ],
        ]);
    }

    public function search(\WP_REST_Request $req): \WP_REST_Response
    {
        $post_type = sanitize_key((string) $req->get_param('post_type'));
        if (!$this->types->is_enabled($post_type)) {
            return new \WP_REST_Response(['error' => 'post_type_not_enabled'], 400);
        }

        $status = (string) ($req->get_param('status') ?: 'publish');
        $q      = trim((string) ($req->get_param('q') ?? ''));
        $preset = (string) ($req->get_param('preset') ?? '');

        // Pagination — `per_page` (1-100, default 20) and `page` (1+, default 1).
        // Falls back to the legacy `limit` param so the Smart Optimizer picker
        // (which uses limit only) keeps working unchanged.
        $per_page = (int) ($req->get_param('per_page') ?? 0);
        if ($per_page <= 0) {
            $per_page = (int) ($req->get_param('limit') ?: 20);
        }
        $per_page = max(1, min(100, $per_page));
        $page     = max(1, (int) ($req->get_param('page') ?: 1));

        $args = self::build_query_args($post_type, [
            'status' => $status,
            'q'      => $q,
            'preset' => $preset,
        ]);
        $args['posts_per_page'] = $per_page;
        $args['paged']          = $page;
        // Need totals so the UI can render proper pagination — keep no_found_rows OFF.
        $args['no_found_rows']  = false;

        $query = new \WP_Query($args);
        $items = [];
        foreach ($query->posts as $p) {
            $thumb = get_the_post_thumbnail_url($p->ID, 'thumbnail') ?: '';
            $items[] = [
                'id'        => (int) $p->ID,
                'title'     => (string) $p->post_title,
                'status'    => (string) $p->post_status,
                'permalink' => (string) get_permalink($p),
                'edit_url'  => (string) get_edit_post_link($p->ID, ''),
                'thumb'     => $thumb,
                'snapshot'  => $this->seo_snapshot((int) $p->ID),
            ];
        }

        return rest_ensure_response([
            'items'       => $items,
            'page'        => $page,
            'per_page'    => $per_page,
            'total'       => (int) $query->found_posts,
            'total_pages' => (int) $query->max_num_pages,
        ]);
    }

    /**
     * Builds shared WP_Query args for both the search endpoint and BulkRunner's
     * filter-mode enqueue path. Returns a base array — caller adds pagination
     * (`posts_per_page`, `paged`, `fields`, `no_found_rows`) as needed.
     *
     * @param array{status?:string,q?:string,preset?:string} $filter
     */
    public static function build_query_args(string $post_type, array $filter): array
    {
        $status = (string) ($filter['status'] ?? 'publish');
        $q      = trim((string) ($filter['q'] ?? ''));
        $preset = (string) ($filter['preset'] ?? '');

        $args = [
            'post_type'   => $post_type,
            'post_status' => $status === 'any' ? ['publish', 'draft', 'pending', 'private'] : $status,
            'orderby'     => 'date',
            'order'       => 'DESC',
            's'           => $q,
        ];

        if ($preset === 'missing_meta') {
            $args['meta_query'] = [
                'relation' => 'AND',
                ['relation' => 'OR',
                    ['key' => '_yoast_wpseo_metadesc', 'compare' => 'NOT EXISTS'],
                    ['key' => '_yoast_wpseo_metadesc', 'value' => '', 'compare' => '='],
                ],
                ['relation' => 'OR',
                    ['key' => 'rank_math_description', 'compare' => 'NOT EXISTS'],
                    ['key' => 'rank_math_description', 'value' => '', 'compare' => '='],
                ],
                ['relation' => 'OR',
                    ['key' => '_aioseo_description', 'compare' => 'NOT EXISTS'],
                    ['key' => '_aioseo_description', 'value' => '', 'compare' => '='],
                ],
            ];
        } elseif ($preset === 'missing_focus') {
            $args['meta_query'] = [
                'relation' => 'OR',
                ['key' => 'rank_math_focus_keyword', 'compare' => 'NOT EXISTS'],
                ['key' => '_yoast_wpseo_focuskw',   'compare' => 'NOT EXISTS'],
                ['key' => '_aioseo_keyphrases',     'compare' => 'NOT EXISTS'],
            ];
        }

        return $args;
    }

    /** Quick read of current SEO meta for the picker UI. */
    private function seo_snapshot(int $post_id): array
    {
        $title = get_post_meta($post_id, 'rank_math_title', true)
              ?: get_post_meta($post_id, '_yoast_wpseo_title', true)
              ?: get_post_meta($post_id, '_aioseo_title', true);
        $desc  = get_post_meta($post_id, 'rank_math_description', true)
              ?: get_post_meta($post_id, '_yoast_wpseo_metadesc', true)
              ?: get_post_meta($post_id, '_aioseo_description', true);
        $kw    = get_post_meta($post_id, 'rank_math_focus_keyword', true)
              ?: get_post_meta($post_id, '_yoast_wpseo_focuskw', true)
              ?: get_post_meta($post_id, '_aioseo_keyphrases', true);
        return [
            'title_len' => is_string($title) ? mb_strlen($title) : 0,
            'desc_len'  => is_string($desc)  ? mb_strlen($desc)  : 0,
            'has_focus' => is_string($kw) && trim($kw) !== '',
        ];
    }
}
