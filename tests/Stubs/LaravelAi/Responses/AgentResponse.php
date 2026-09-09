<?php

declare(strict_types=1);

namespace Laravel\Ai\Responses;

use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;

class AgentResponse extends TextResponse
{
    public string $invocationId;

    public ?string $conversationId = null;

    public ?object $conversationUser = null;

    public function __construct(string $invocationId, string $text, Usage $usage, Meta $meta)
    {
        $this->invocationId = $invocationId;

        parent::__construct($text, $usage, $meta);
    }

    public function withinConversation(?string $conversationId, ?object $conversationUser = null): self
    {
        $this->conversationId = $conversationId;
        $this->conversationUser = $conversationUser;

        return $this;
    }
}
