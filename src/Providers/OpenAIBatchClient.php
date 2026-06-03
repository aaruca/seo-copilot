<?php

namespace SeoCopilot\Providers;

use SeoCopilot\Support\Logger;

/**
 * Thin wrapper around OpenAI's Files + Batches REST APIs.
 *
 * Used by BatchDispatcher for the async bulk path. Distinct from
 * OpenAIProvider (which handles synchronous chat-completions) because the
 * Batch API has its own request lifecycle, file uploads, and polling.
 */
class OpenAIBatchClient
{
    private const FILES_ENDPOINT   = 'https://api.openai.com/v1/files';
    private const BATCHES_ENDPOINT = 'https://api.openai.com/v1/batches';

    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function api_key(): string
    {
        $settings = get_option('seocp_settings', []);
        return trim((string) ($settings['openai_api_key'] ?? ''));
    }

    /**
     * Upload a local JSONL file with purpose=batch. Returns the OpenAI file id.
     *
     * Uses cURL with CURLFile so a 100 MB JSONL is streamed from disk instead
     * of buffered into PHP memory.
     */
    public function upload_file(string $path): string
    {
        $key = $this->api_key();
        if ($key === '') {
            throw new \RuntimeException('OpenAI API key is not configured.');
        }
        if (!is_readable($path)) {
            throw new \RuntimeException('Batch input file not readable: ' . $path);
        }
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('PHP cURL is required for OpenAI Batch uploads.');
        }

        $ch = curl_init(self::FILES_ENDPOINT);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $key]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'purpose' => 'batch',
            'file'    => new \CURLFile($path, 'application/jsonl', basename($path)),
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new \RuntimeException('OpenAI file upload failed: ' . $err);
        }
        $data = json_decode((string) $raw, true);
        if ($code !== 200 || !is_array($data) || empty($data['id'])) {
            $msg = is_array($data) ? ($data['error']['message'] ?? 'HTTP ' . $code) : 'HTTP ' . $code;
            throw new \RuntimeException('OpenAI file upload error: ' . $msg);
        }
        return (string) $data['id'];
    }

    /**
     * Create a new batch on /v1/batches.
     *
     * @param array<string,mixed> $metadata Optional metadata attached to the batch.
     * @return array{id:string,status:string,raw:array<string,mixed>}
     */
    public function create_batch(string $input_file_id, array $metadata = []): array
    {
        $key = $this->api_key();
        if ($key === '') {
            throw new \RuntimeException('OpenAI API key is not configured.');
        }
        $payload = [
            'input_file_id'     => $input_file_id,
            'endpoint'          => '/v1/chat/completions',
            'completion_window' => '24h',
        ];
        if ($metadata) {
            $payload['metadata'] = $metadata;
        }

        $response = wp_remote_post(self::BATCHES_ENDPOINT, [
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 60,
        ]);
        if (is_wp_error($response)) {
            throw new \RuntimeException('OpenAI create-batch failed: ' . $response->get_error_message());
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $raw  = (string) wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);
        if ($code !== 200 || !is_array($data) || empty($data['id'])) {
            $msg = is_array($data) ? ($data['error']['message'] ?? 'HTTP ' . $code) : 'HTTP ' . $code;
            throw new \RuntimeException('OpenAI create-batch error: ' . $msg);
        }
        return [
            'id'     => (string) $data['id'],
            'status' => (string) ($data['status'] ?? 'validating'),
            'raw'    => $data,
        ];
    }

    /**
     * Fetch the current status of an OpenAI batch.
     *
     * @return array<string,mixed>
     */
    public function get_batch(string $openai_batch_id): array
    {
        $key = $this->api_key();
        if ($key === '') {
            throw new \RuntimeException('OpenAI API key is not configured.');
        }
        $response = wp_remote_get(self::BATCHES_ENDPOINT . '/' . rawurlencode($openai_batch_id), [
            'headers' => ['Authorization' => 'Bearer ' . $key],
            'timeout' => 30,
        ]);
        if (is_wp_error($response)) {
            throw new \RuntimeException('OpenAI get-batch failed: ' . $response->get_error_message());
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $raw  = (string) wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);
        if ($code !== 200 || !is_array($data)) {
            $msg = is_array($data) ? ($data['error']['message'] ?? 'HTTP ' . $code) : 'HTTP ' . $code;
            throw new \RuntimeException('OpenAI get-batch error: ' . $msg);
        }
        return $data;
    }

    /**
     * Cancel an in-progress OpenAI batch.
     *
     * @return array<string,mixed>
     */
    public function cancel_batch(string $openai_batch_id): array
    {
        $key = $this->api_key();
        if ($key === '') {
            throw new \RuntimeException('OpenAI API key is not configured.');
        }
        $response = wp_remote_post(self::BATCHES_ENDPOINT . '/' . rawurlencode($openai_batch_id) . '/cancel', [
            'headers' => ['Authorization' => 'Bearer ' . $key],
            'timeout' => 30,
        ]);
        if (is_wp_error($response)) {
            throw new \RuntimeException('OpenAI cancel-batch failed: ' . $response->get_error_message());
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $raw  = (string) wp_remote_retrieve_body($response);
        $data = json_decode($raw, true) ?: [];
        if ($code !== 200) {
            $msg = $data['error']['message'] ?? 'HTTP ' . $code;
            throw new \RuntimeException('OpenAI cancel-batch error: ' . $msg);
        }
        return $data;
    }

    /**
     * Download the raw contents of a file (the output JSONL for a completed batch).
     */
    public function get_file_content(string $file_id): string
    {
        $key = $this->api_key();
        if ($key === '') {
            throw new \RuntimeException('OpenAI API key is not configured.');
        }
        $response = wp_remote_get(self::FILES_ENDPOINT . '/' . rawurlencode($file_id) . '/content', [
            'headers' => ['Authorization' => 'Bearer ' . $key],
            'timeout' => 600,
        ]);
        if (is_wp_error($response)) {
            throw new \RuntimeException('OpenAI file-content failed: ' . $response->get_error_message());
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $raw = (string) wp_remote_retrieve_body($response);
            $data = json_decode($raw, true);
            $msg = is_array($data) ? ($data['error']['message'] ?? 'HTTP ' . $code) : 'HTTP ' . $code;
            throw new \RuntimeException('OpenAI file-content error: ' . $msg);
        }
        return (string) wp_remote_retrieve_body($response);
    }
}
