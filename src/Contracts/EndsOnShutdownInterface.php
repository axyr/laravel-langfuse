<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Contracts;

/**
 * An observation that is still being built in memory and has to be exported
 * before the process goes away.
 */
interface EndsOnShutdownInterface
{
    public function endOnShutdown(): void;

    public function hasEnded(): bool;
}
