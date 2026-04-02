<?php

declare(strict_types=1);

namespace Laravel\Ai\Responses;

use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;

class StreamedAgentResponse extends AgentResponse
{
    public function __construct(string $invocationId, string $text, Usage $usage, Meta $meta)
    {
        parent::__construct($invocationId, $text, $usage, $meta);
    }
}
