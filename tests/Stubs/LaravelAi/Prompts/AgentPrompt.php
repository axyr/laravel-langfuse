<?php

declare(strict_types=1);

namespace Laravel\Ai\Prompts;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Providers\TextProvider;

class AgentPrompt extends Prompt
{
    public readonly Agent $agent;

    public function __construct(
        Agent $agent,
        string $prompt,
        TextProvider $provider,
        string $model,
    ) {
        parent::__construct($prompt, $provider, $model);

        $this->agent = $agent;
    }
}
