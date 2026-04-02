<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Objects;

use Axyr\Langfuse\Dto\Usage;
use Axyr\Langfuse\Enums\ObservationLevel;

class NullLangfuseGeneration extends LangfuseGeneration
{
    public function __construct() {}

    public function getId(): string
    {
        return '';
    }

    public function getTraceId(): ?string
    {
        return null;
    }

    public function end(
        ?string $endTime = null,
        mixed $output = null,
        ?Usage $usage = null,
        ?string $statusMessage = null,
        ?ObservationLevel $level = null,
    ): void {}
}
