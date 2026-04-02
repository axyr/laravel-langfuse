<?php

declare(strict_types=1);

namespace Laravel\Ai\Responses\Data;

class Usage
{
    public function __construct(
        public int $promptTokens = 0,
        public int $completionTokens = 0,
        public int $cacheWriteInputTokens = 0,
        public int $cacheReadInputTokens = 0,
        public int $reasoningTokens = 0,
    ) {}
}
