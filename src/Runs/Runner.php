<?php

namespace SeoCopilot\Runs;

use SeoCopilot\Fields\FieldRegistry;
use SeoCopilot\Providers\OpenAIProvider;
use SeoCopilot\Providers\PromptAssembler;
use SeoCopilot\Support\Logger;
use SeoCopilot\Templates\Template;
use SeoCopilot\Templates\TemplateRepository;

class Runner
{
    private TemplateRepository $templates;
    private FieldRegistry $fields;
    private PromptAssembler $assembler;
    private OpenAIProvider $ai;
    private RunRepository $runs;
    private Logger $logger;

    public function __construct(
        TemplateRepository $templates,
        FieldRegistry $fields,
        PromptAssembler $assembler,
        OpenAIProvider $ai,
        RunRepository $runs,
        Logger $logger
    ) {
        $this->templates = $templates;
        $this->fields    = $fields;
        $this->assembler = $assembler;
        $this->ai        = $ai;
        $this->runs      = $runs;
        $this->logger    = $logger;
    }

    /**
     * Generates a proposal — does NOT write to the post.
     *
     * @param array<int, string> $picked_fields
     * @return array{proposal:array<string,string>,run:Run}
     */
    public function generate(int $post_id, int $template_id, array $picked_fields, ?string $batch_id = null): array
    {
        $tpl = $this->templates->find($template_id);
        if (!$tpl) {
            throw new \RuntimeException('Template not found.');
        }

        $allowed = $this->intersect_picked($tpl, $picked_fields, get_post_type($post_id) ?: '');
        if ($allowed === []) {
            throw new \RuntimeException('No allowed fields after intersecting template + picker.');
        }

        $prompt = $this->assembler->assemble($tpl, $post_id, $allowed);
        $result = $this->ai->complete_json($prompt['system'], $prompt['user']);

        $payload = json_decode($result['content'], true);
        if (!is_array($payload)) {
            throw new \RuntimeException('AI did not return valid JSON.');
        }

        // Filter — drop anything not in the picked set.
        $proposal = [];
        foreach ($allowed as $fid) {
            if (isset($payload[$fid]) && is_string($payload[$fid])) {
                $proposal[$fid] = $payload[$fid];
            } elseif (isset($payload[$fid]) && (is_int($payload[$fid]) || is_float($payload[$fid]))) {
                $proposal[$fid] = (string) $payload[$fid];
            }
        }

        // Hard guardrails for products. The prompts ask the AI to follow these,
        // but we re-enforce on the server because the AI occasionally drifts
        // (especially on long product names or with multi-keyword strings).
        $this->enforce_product_name_in_title($post_id, $proposal);
        $this->enforce_product_name_in_keywords($post_id, $proposal);

        $run = new Run();
        $run->post_id     = $post_id;
        $run->post_type   = (string) get_post_type($post_id);
        $run->template_id = $tpl->id;
        $run->status      = 'proposed';
        $run->fields_written = [];
        $run->tokens_in   = $result['tokens_in'];
        $run->tokens_out  = $result['tokens_out'];
        $run->cost        = (float) $result['cost'];
        $run->model       = $result['model'];
        $run->batch_id    = $batch_id;
        $this->runs->record($run);

        return ['proposal' => $proposal, 'run' => $run];
    }

    /**
     * Apply a proposal — writes only the fields supplied (after intersecting with the template).
     *
     * @param array<string, string> $values
     * @return array<int, string> field ids actually written
     */
    public function apply(int $post_id, ?int $template_id, array $values, ?string $batch_id = null): array
    {
        $post_type = get_post_type($post_id) ?: '';
        $allowed_ids = array_keys($values);

        if ($template_id) {
            $tpl = $this->templates->find($template_id);
            if ($tpl) {
                $allowed_ids = $this->intersect_picked($tpl, array_keys($values), $post_type);
            }
        }

        // Re-enforce on apply too, in case a user edited the proposed value
        // and removed the product name in the Smart Optimizer review screen.
        $this->enforce_product_name_in_title($post_id, $values);
        $this->enforce_product_name_in_keywords($post_id, $values);

        $written = [];
        foreach ($allowed_ids as $fid) {
            $field = $this->fields->get($fid);
            if (!$field || !$field->applies_to($post_type)) {
                continue;
            }
            if (!isset($values[$fid])) {
                continue;
            }
            $value = (string) $values[$fid];
            if ($field->max_length > 0) {
                $value = function_exists('mb_substr') ? mb_substr($value, 0, $field->max_length) : substr($value, 0, $field->max_length);
            }
            if ($field->write($post_id, $value)) {
                $written[] = $fid;
            }
        }

        $run = new Run();
        $run->post_id        = $post_id;
        $run->post_type      = $post_type;
        $run->template_id    = $template_id;
        $run->status         = $written ? 'applied' : 'noop';
        $run->fields_written = $written;
        $run->batch_id       = $batch_id;
        $this->runs->record($run);

        return $written;
    }

    /**
     * For product runs, ensure every SEO title field contains the original
     * product name verbatim (case-insensitive substring). If the AI omitted
     * it, we prepend the product name and trim the combined title to 60 chars
     * (the Rank Math green-band cap).
     *
     * @param array<string, string> $values  Mutated in place.
     */
    private function enforce_product_name_in_title(int $post_id, array &$values): void
    {
        $post_type = get_post_type($post_id) ?: '';
        if ($post_type !== 'product') {
            return;
        }
        $name = trim((string) get_the_title($post_id));
        if ($name === '') {
            return;
        }
        $title_keys = ['rm_seo_title', 'yoast_seo_title', 'aioseo_title', 'seopress_seo_title'];
        foreach ($title_keys as $k) {
            if (!isset($values[$k])) continue;
            $proposed = trim((string) $values[$k]);
            if ($proposed === '') {
                $values[$k] = $name;
                continue;
            }
            // Already contains the product name — leave it alone.
            if (function_exists('mb_stripos')) {
                $found = mb_stripos($proposed, $name) !== false;
            } else {
                $found = stripos($proposed, $name) !== false;
            }
            if ($found) continue;

            // Repair: prepend the product name. If the combined string blows
            // the 60-char budget, prefer the product name over the AI's add-on.
            $max = 60;
            $strlen = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';
            $substr = function_exists('mb_substr') ? 'mb_substr' : 'substr';
            $name_len = $strlen($name);
            if ($name_len >= $max) {
                $values[$k] = $substr($name, 0, $max);
                continue;
            }
            $sep = ' — ';
            $remaining = $max - $name_len - $strlen($sep);
            if ($remaining < 6) {
                $values[$k] = $name;
                continue;
            }
            $values[$k] = $name . $sep . $substr($proposed, 0, $remaining);
        }
    }

    /**
     * For product runs, ensure every focus-keyword field has the product name
     * as the FIRST comma-separated token. AI is instructed to do this in the
     * prompt, but we re-check on the server. If the first token (case-insensitive)
     * is not the product name, prepend it; existing tokens are de-duplicated and
     * the whole string is capped at 200 chars (the field's max_length).
     *
     * @param array<string, string> $values  Mutated in place.
     */
    private function enforce_product_name_in_keywords(int $post_id, array &$values): void
    {
        $post_type = get_post_type($post_id) ?: '';
        if ($post_type !== 'product') {
            return;
        }
        $name = trim((string) get_the_title($post_id));
        if ($name === '') {
            return;
        }
        $strlen   = function_exists('mb_strlen')   ? 'mb_strlen'   : 'strlen';
        $strtolow = function_exists('mb_strtolower') ? 'mb_strtolower' : 'strtolower';
        $substr   = function_exists('mb_substr')   ? 'mb_substr'   : 'substr';
        $name_lc  = $strtolow($name);

        foreach (['rm_focus_keyword', 'yoast_focus_keyword', 'aioseo_keyphrase', 'seopress_focus_keyword'] as $k) {
            if (!isset($values[$k])) continue;
            $raw = trim((string) $values[$k]);

            // Split on comma or semicolon, normalise whitespace, drop empties.
            $tokens = preg_split('/[,;]+/', $raw) ?: [];
            $tokens = array_values(array_filter(array_map(static function ($t) {
                return trim(preg_replace('/\s+/u', ' ', (string) $t) ?? '');
            }, $tokens), static fn($t) => $t !== ''));

            $first_lc = $tokens ? $strtolow($tokens[0]) : '';
            if ($first_lc !== $name_lc) {
                // Drop any later occurrence of the product name to avoid duplicates,
                // then prepend it as token #1.
                $tokens = array_values(array_filter($tokens, static fn($t) => $strtolow($t) !== $name_lc));
                array_unshift($tokens, $name);
            }

            // Cap at max_length 200 (matches the Field's declared max_length).
            $combined = implode(', ', $tokens);
            if ($strlen($combined) > 200) {
                // Trim from the end (drop long-tail variants) until under the cap.
                while (count($tokens) > 1 && $strlen(implode(', ', $tokens)) > 200) {
                    array_pop($tokens);
                }
                $combined = implode(', ', $tokens);
                if ($strlen($combined) > 200) {
                    // Product name alone is over budget — truncate just the name.
                    $combined = $substr($name, 0, 200);
                }
            }
            $values[$k] = $combined;
        }
    }

    /**
     * @param array<int, string> $picked
     * @return array<int, string>
     */
    private function intersect_picked(Template $tpl, array $picked, string $post_type): array
    {
        $picked = array_values(array_unique(array_map('strval', $picked)));
        if ($tpl->produces) {
            $picked = array_values(array_intersect($picked, $tpl->produces));
        }
        // Drop any field not registered or not applicable to the post type.
        $out = [];
        foreach ($picked as $fid) {
            $field = $this->fields->get($fid);
            if ($field && $field->applies_to($post_type)) {
                $out[] = $fid;
            }
        }
        // Hard guardrail: products never receive product-page copy from the AI,
        // even if a user enables those fields for the `product` CPT manually.
        if ($post_type === 'product') {
            $blocked = ['post_title', 'post_content', 'post_excerpt', 'wc_short_description', 'wc_long_description', 'featured_image_alt'];
            $out = array_values(array_diff($out, $blocked));
        }
        return $out;
    }
}
