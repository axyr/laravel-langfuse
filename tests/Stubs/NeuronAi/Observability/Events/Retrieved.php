<?php

declare(strict_types=1);

namespace NeuronAI\Observability\Events;

use NeuronAI\Chat\Messages\Message;

class Retrieved
{
    /**
     * @param array<mixed> $documents
     */
    public function __construct(
        public Message $question,
        public array $documents,
    ) {}
}
