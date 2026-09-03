<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Contracts;

interface HasSessionIdInterface
{
    /**
     * Get the identifier to tag auto-instrumented traces with as the Langfuse sessionId.
     */
    public function getSessionId(): ?string;
}
