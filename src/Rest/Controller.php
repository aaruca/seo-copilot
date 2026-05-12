<?php

namespace SeoCopilot\Rest;

use SeoCopilot\Capabilities\Capabilities;

abstract class Controller
{
    protected string $namespace = SEOCP_REST_NS;

    abstract public function register_routes(): void;

    public function permit_run(): bool
    {
        return Capabilities::user_can_run();
    }

    public function permit_manage(): bool
    {
        return Capabilities::user_can_manage();
    }

    public function permit_edit_templates(): bool
    {
        return Capabilities::user_can_edit_templates();
    }
}
