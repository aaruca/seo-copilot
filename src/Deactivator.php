<?php

namespace SeoCopilot;

class Deactivator
{
    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('seocp_run_bulk_batch');
    }
}
