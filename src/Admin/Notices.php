<?php

namespace SeoCopilot\Admin;

class Notices
{
    public static function success(string $msg): void
    {
        add_action('admin_notices', static function () use ($msg) {
            echo '<div class="notice notice-success"><p>' . esc_html($msg) . '</p></div>';
        });
    }

    public static function error(string $msg): void
    {
        add_action('admin_notices', static function () use ($msg) {
            echo '<div class="notice notice-error"><p>' . esc_html($msg) . '</p></div>';
        });
    }
}
