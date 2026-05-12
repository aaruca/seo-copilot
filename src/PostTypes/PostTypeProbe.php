<?php

namespace SeoCopilot\PostTypes;

class PostTypeProbe
{
    /**
     * Returns all public post types as [slug => label, ...].
     * Excludes attachment, revision, nav_menu_item, etc.
     *
     * @return array<string, string>
     */
    public function discover(): array
    {
        $types = get_post_types(['public' => true], 'objects');
        $out = [];
        $exclude = ['attachment'];
        foreach ($types as $slug => $obj) {
            if (in_array($slug, $exclude, true)) {
                continue;
            }
            $out[$slug] = $obj->labels->singular_name ?? $obj->label ?? $slug;
        }
        return $out;
    }
}
