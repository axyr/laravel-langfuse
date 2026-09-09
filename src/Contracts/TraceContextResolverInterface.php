<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Contracts;

/**
 * Supplies default userId and sessionId values for traces that do not set
 * their own. Bind your own implementation to change where they come from.
 */
interface TraceContextResolverInterface
{
    public function resolveUserId(): ?string;

    public function resolveSessionId(): ?string;
}
