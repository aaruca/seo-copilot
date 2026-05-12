<?php

namespace SeoCopilot\Support;

/**
 * Reads Bricks Builder JSON meta and concatenates the readable text
 * (headings, basic text, rich text, button labels) so the AI can use it
 * as `{{builder_plain_text}}` when `post_content` is empty.
 *
 * Limitations: dynamic-data tags, query loops, and template-included
 * elements are intentionally NOT resolved — we capture the literal
 * content present in the page's own _bricks_page_content_2 meta only.
 */
class BricksContentExtractor
{
    public const META_KEY = '_bricks_page_content_2';

    /** Heuristic list of element names whose `settings.text` / `content` we treat as readable. */
    private const READABLE = [
        'heading' => true,
        'text' => true,
        'text-basic' => true,
        'text-link' => true,
        'rich-text' => true,
        'button' => true,
        'list' => true,
        'icon-list' => true,
        'alert' => true,
        'tabs' => true,
        'accordion' => true,
        'tooltip' => true,
        'post-title' => false, // skip dynamic-only
    ];

    public function is_active(): bool
    {
        return defined('BRICKS_VERSION') || class_exists('\\Bricks\\Database');
    }

    public function extract(int $post_id, int $cap = 8000): string
    {
        $raw = get_post_meta($post_id, self::META_KEY, true);
        if (!$raw) {
            return '';
        }
        $data = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($data)) {
            return '';
        }

        $bag = [];
        $this->walk($data, $bag);
        $text = implode("\n", array_filter(array_map('trim', $bag)));
        $text = wp_strip_all_tags($text, true);
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        if ($cap > 0 && strlen($text) > $cap) {
            $text = substr($text, 0, $cap) . '…';
        }
        return trim($text);
    }

    private function walk(array $nodes, array &$bag): void
    {
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            $name = isset($node['name']) ? (string) $node['name'] : '';
            $skip = isset(self::READABLE[$name]) && self::READABLE[$name] === false;

            if (!$skip && isset($node['settings']) && is_array($node['settings'])) {
                foreach (['text', 'content', 'title', 'subtitle', 'label', 'heading'] as $k) {
                    if (!empty($node['settings'][$k]) && is_string($node['settings'][$k])) {
                        $bag[] = $node['settings'][$k];
                    }
                }
                // Nested items (accordion, tabs, list, icon-list).
                foreach (['items', 'tabs', 'accordions'] as $k) {
                    if (!empty($node['settings'][$k]) && is_array($node['settings'][$k])) {
                        foreach ($node['settings'][$k] as $sub) {
                            if (is_array($sub)) {
                                foreach (['title', 'content', 'label', 'text'] as $kk) {
                                    if (!empty($sub[$kk]) && is_string($sub[$kk])) {
                                        $bag[] = $sub[$kk];
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if (!empty($node['children']) && is_array($node['children'])) {
                $this->walk($node['children'], $bag);
            }
        }
    }
}
