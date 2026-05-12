<?php

namespace SeoCopilot\Providers;

use SeoCopilot\Support\Logger;
use SeoCopilot\Support\RateLimiter;

class OpenAIProvider implements AIProvider
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /** Approximate USD per 1K tokens. Override via filter `seocp_openai_pricing`. */
    private const PRICING = [
        'gpt-4.1'        => ['in' => 0.0030, 'out' => 0.0120],
        'gpt-4.1-mini'   => ['in' => 0.0008, 'out' => 0.0032],
        'gpt-4.1-nano'   => ['in' => 0.0002, 'out' => 0.0008],
        'gpt-4o'         => ['in' => 0.0050, 'out' => 0.0150],
        'gpt-4o-mini'    => ['in' => 0.0006, 'out' => 0.0024],
    ];

    private Logger $logger;
    private RateLimiter $limiter;

    public function __construct(Logger $logger, RateLimiter $limiter)
    {
        $this->logger  = $logger;
        $this->limiter = $limiter;
    }

    public function complete_json(string $system, string $user, array $opts = []): array
    {
        $settings = get_option('seocp_settings', []);
        $key      = trim((string) ($settings['openai_api_key'] ?? ''));
        $model    = (string) ($opts['model'] ?? $settings['openai_model'] ?? 'gpt-4.1-mini');
        $rpm      = isset($settings['rate_per_min']) ? (int) $settings['rate_per_min'] : 30;

        if ($key === '') {
            throw new \RuntimeException('OpenAI API key is not configured.');
        }
        $bucket = 'openai_' . get_current_user_id();
        if (!$this->limiter->allow($bucket, $rpm)) {
            throw new \RuntimeException('Local rate limit reached — please wait a minute.');
        }

        $body = [
            'model'           => $model,
            'response_format' => ['type' => 'json_object'],
            'messages'        => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
            'temperature'     => isset($opts['temperature']) ? (float) $opts['temperature'] : 0.6,
        ];
        if (isset($opts['max_tokens'])) {
            $body['max_tokens'] = (int) $opts['max_tokens'];
        }

        $response = wp_remote_post(self::ENDPOINT, [
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            $this->logger->error('OpenAI request failed', ['msg' => $response->get_error_message()]);
            throw new \RuntimeException('OpenAI request failed: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);

        if ($code !== 200 || !is_array($data)) {
            $msg = $data['error']['message'] ?? 'HTTP ' . $code;
            $this->logger->error('OpenAI API error', ['code' => $code, 'msg' => $msg]);
            throw new \RuntimeException('OpenAI API error: ' . $msg);
        }

        $content   = $data['choices'][0]['message']['content'] ?? '';
        $tokens_in = (int) ($data['usage']['prompt_tokens'] ?? 0);
        $tokens_out= (int) ($data['usage']['completion_tokens'] ?? 0);

        $pricing = apply_filters('seocp_openai_pricing', self::PRICING);
        $rate    = $pricing[$model] ?? ['in' => 0.001, 'out' => 0.003];
        $cost    = ($tokens_in / 1000.0) * $rate['in'] + ($tokens_out / 1000.0) * $rate['out'];

        return [
            'content'    => (string) $content,
            'tokens_in'  => $tokens_in,
            'tokens_out' => $tokens_out,
            'cost'       => round($cost, 6),
            'model'      => $model,
            'raw'        => $data,
        ];
    }

    public static function models(): array
    {
        return array_keys(self::PRICING);
    }
}
