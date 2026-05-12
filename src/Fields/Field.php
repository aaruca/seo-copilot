<?php

namespace SeoCopilot\Fields;

/**
 * Immutable value-object for a writable SEO field.
 * `reader` returns current value for a post id; `writer` writes a new value.
 */
class Field
{
    public string $id;
    public string $label;
    public string $group;
    public string $description;
    public int $max_length;
    /** @var callable(int):string */
    public $reader;
    /** @var callable(int,string):bool */
    public $writer;
    /** @var array<int, string> */
    public array $applies_to;

    public function __construct(
        string $id,
        string $label,
        string $group,
        callable $reader,
        callable $writer,
        array $applies_to = [],
        int $max_length = 0,
        string $description = ''
    ) {
        $this->id          = $id;
        $this->label       = $label;
        $this->group       = $group;
        $this->reader      = $reader;
        $this->writer      = $writer;
        $this->applies_to  = $applies_to;
        $this->max_length  = $max_length;
        $this->description = $description;
    }

    public function applies_to(string $post_type): bool
    {
        return $this->applies_to === [] || in_array($post_type, $this->applies_to, true);
    }

    public function read(int $post_id): string
    {
        return (string) call_user_func($this->reader, $post_id);
    }

    public function write(int $post_id, string $value): bool
    {
        return (bool) call_user_func($this->writer, $post_id, $value);
    }

    public function to_array(): array
    {
        return [
            'id'          => $this->id,
            'label'       => $this->label,
            'group'       => $this->group,
            'description' => $this->description,
            'max_length'  => $this->max_length,
        ];
    }
}
