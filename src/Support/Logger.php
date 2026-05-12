<?php

namespace SeoCopilot\Support;

class Logger
{
    public function debug(string $msg, array $ctx = []): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->write('DEBUG', $msg, $ctx);
        }
    }

    public function info(string $msg, array $ctx = []): void
    {
        $this->write('INFO', $msg, $ctx);
    }

    public function error(string $msg, array $ctx = []): void
    {
        $this->write('ERROR', $msg, $ctx);
    }

    private function write(string $level, string $msg, array $ctx): void
    {
        $line = sprintf('[seo-copilot][%s] %s', $level, $msg);
        if ($ctx) {
            $line .= ' ' . wp_json_encode($ctx);
        }
        if (function_exists('error_log')) {
            error_log($line);
        }
    }
}
