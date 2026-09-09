<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Tracing;

use Axyr\Langfuse\Contracts\TraceContextResolverInterface;

class NullTraceContextResolver implements TraceContextResolverInterface
{
    public function resolveUserId(): ?string
    {
        return null;
    }

    public function resolveSessionId(): ?string
    {
        return null;
    }
}
