<?php

declare(strict_types=1);

namespace Langfuse\Dto;

readonly class IngestionError
{
    public function __construct(
        public string $id,
        public int $status,
        public string $message,
        public ?string $error = null,
    ) {}
}
