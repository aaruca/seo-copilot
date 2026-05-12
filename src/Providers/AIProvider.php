<?php

namespace SeoCopilot\Providers;

interface AIProvider
{
    /**
     * Performs a JSON-mode chat completion.
     *
     * @return array{content:string,tokens_in:int,tokens_out:int,cost:float,model:string,raw:array}
     */
    public function complete_json(string $system, string $user, array $opts = []): array;
}
