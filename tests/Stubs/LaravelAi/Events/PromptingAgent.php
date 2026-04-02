<?php

declare(strict_types=1);

namespace Laravel\Ai\Events;

use Laravel\Ai\Prompts\AgentPrompt;

class PromptingAgent
{
    public function __construct(
        public string $invocationId,
        public AgentPrompt $prompt,
    ) {}
}
