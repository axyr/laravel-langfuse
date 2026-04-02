<?php

declare(strict_types=1);

namespace Laravel\Ai\Responses;

use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;

class TextResponse
{
    public function __construct(
        public string $text,
        public Usage $usage,
        public Meta $meta,
    ) {}
}
