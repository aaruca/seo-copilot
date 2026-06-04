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
            $mq = self::missing_query(self::active_desc_keys());
            if ($mq) {
                $args['meta_query'] = $mq;
            }
        } elseif ($preset === 'missing_focus') {
            $mq = self::missing_query(self::active_focus_keys());
            if ($mq) {
                $args['meta_query'] = $mq;
            }
        }

        return $args;
    }

    /**
     * Build a "missing across every listed key" meta_query: a post matches only
     * when the field is absent OR empty for ALL of the given keys.
     *
     * Relation is AND (not OR). Using OR was the bug — when only one SEO plugin
     * is installed, the other plugins' keys never exist, so "missing in plugin
     * X OR plugin Y" matched every post. With AND, an inactive plugin's key
     * (always absent) is trivially satisfied, so the condition reduces to the
     * active plugin(s) — which is what we want.
     *
     * @param array<int,string> $keys
     * @return array<string,mixed>
     */
    private static function missing_query(array $keys): array
    {
        if (!$keys) {
            return [];
        }
        $mq = ['relation' => 'AND'];
        foreach ($keys as $key) {
            $mq[] = [
                'relation' => 'OR',
                ['key' => $key, 'compare' => 'NOT EXISTS'],
                ['key' => $key, 'value' => '', 'compare' => '='],
            ];
        }
        return $mq;
    }

    /**
     * Meta-description keys for the SEO plugins that are actually active. Keeps
     * the JOIN count down on large catalogs and avoids the inactive-plugin
     * false-positive. Falls back to all known keys if none is detected.
     *
     * @return array<int,string>
     */
    private static function active_desc_keys(): array
    {
        $keys = [];
        if (defined('RANK_MATH_VERSION') || class_exists('RankMath'))   $keys[] = 'rank_math_description';
        if (defined('WPSEO_VERSION') || class_exists('WPSEO_Options'))  $keys[] = '_yoast_wpseo_metadesc';
        if (defined('AIOSEO_VERSION') || function_exists('aioseo'))     $keys[] = '_aioseo_description';
        return $keys ?: ['rank_math_description', '_yoast_wpseo_metadesc', '_aioseo_description'];
    }

    /**
     * Focus-keyword keys for the active SEO plugins. @see active_desc_keys().
     *
     * @return array<int,string>
     */
    private static function active_focus_keys(): array
    {
        $keys = [];
        if (defined('RANK_MATH_VERSION') || class_exists('RankMath'))   $keys[] = 'rank_math_focus_keyword';
        if (defined('WPSEO_VERSION') || class_exists('WPSEO_Options'))  $keys[] = '_yoast_wpseo_focuskw';
        if (defined('AIOSEO_VERSION') || function_exists('aioseo'))     $keys[] = '_aioseo_keyphrases';
        return $keys ?: ['rank_math_focus_keyword', '_yoast_wpseo_focuskw', '_aioseo_keyphrases'];
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
