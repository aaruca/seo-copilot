<?php

namespace SeoCopilot\Providers;

use SeoCopilot\Support\PostSnapshotFactory;
use SeoCopilot\Templates\Template;

class PromptAssembler
{
    private PostSnapshotFactory $snapshots;

    public function __construct(PostSnapshotFactory $snapshots)
    {
        $this->snapshots = $snapshots;
    }

    /**
     * @param array<int, string> $fields The picked field IDs.
     * @return array{system:string,user:string,placeholders:array<string,string>}
     */
    public function assemble(Template $tpl, int $post_id, array $fields): array
    {
        $placeholders = $this->snapshots->build($post_id);
        $placeholders['fields'] = implode(', ', $fields);

        return [
            'system'       => $this->render($tpl->system_prompt, $placeholders),
            'user'         => $this->render($tpl->user_template, $placeholders),
            'placeholders' => $placeholders,
        ];
    }

    public function render(string $template, array $vars): string
    {
        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', static function ($m) use ($vars) {
            $k = strtolower($m[1]);
            return isset($vars[$k]) ? (string) $vars[$k] : '';
        }, $template) ?? $template;
    }
}
