<?php

namespace SeoCopilot\Runs;

class Run
{
    public ?int $id = null;
    public int $post_id = 0;
    public string $post_type = '';
    public ?int $template_id = null;
    public string $status = 'pending';
    /** @var array<int, string> */
    public array $fields_written = [];
    public int $tokens_in = 0;
    public int $tokens_out = 0;
    public float $cost = 0.0;
    public ?string $model = null;
    public ?string $error_message = null;
    public ?string $batch_id = null;
    public string $created_at = '';

    public function to_array(): array
    {
        return [
            'id'             => $this->id,
            'post_id'        => $this->post_id,
            'post_type'      => $this->post_type,
            'template_id'    => $this->template_id,
            'status'         => $this->status,
            'fields_written' => $this->fields_written,
            'tokens_in'      => $this->tokens_in,
            'tokens_out'     => $this->tokens_out,
            'cost'           => $this->cost,
            'model'          => $this->model,
            'error_message'  => $this->error_message,
            'batch_id'       => $this->batch_id,
            'created_at'     => $this->created_at,
        ];
    }
}
