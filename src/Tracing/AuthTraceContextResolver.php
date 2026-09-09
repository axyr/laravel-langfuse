<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Tracing;

use Axyr\Langfuse\Contracts\TraceContextResolverInterface;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Container\Container;

/**
 * Default resolver: the userId is the user already authenticated on the default
 * guard. The guard is only read, never asked to authenticate, so tracing cannot
 * trigger remember-me logins, token lookups or login events. No default
 * sessionId exists at the framework level; integrations supply their own.
 */
class AuthTraceContextResolver implements TraceContextResolverInterface
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function resolveUserId(): ?string
    {
        if (! $this->container->bound(AuthFactory::class)) {
            return null;
        }

        /** @var AuthFactory $auth */
        $auth = $this->container->make(AuthFactory::class);
        $guard = $auth->guard();

        if (! $guard->hasUser()) {
            return null;
        }

        return TraceUserId::from($guard->user());
    }

    public function resolveSessionId(): ?string
    {
        return null;
    }
}
