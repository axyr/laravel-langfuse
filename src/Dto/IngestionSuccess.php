<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

readonly class IngestionSuccess
{
    public function __construct(
        public string $id,
        public int $status,
    ) {}
}
