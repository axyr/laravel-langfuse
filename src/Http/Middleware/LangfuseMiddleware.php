<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Http\Middleware;

use Axyr\Langfuse\Contracts\LangfuseClientInterface;
use Axyr\Langfuse\Dto\TraceBody;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LangfuseMiddleware
{
    public function __construct(
        private readonly LangfuseClientInterface $langfuse,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->langfuse->isEnabled()) {
            return $next($request);
        }

        $authId = $request->user()?->getAuthIdentifier();

        $trace = $this->langfuse->trace(new TraceBody(
            name: $request->route()?->getName() ?? $request->method() . ' ' . $request->path(),
            userId: is_scalar($authId) ? (string) $authId : null,
            metadata: [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'source' => 'langfuse-middleware',
            ],
        ));

        $this->langfuse->setCurrentTrace($trace);

        return $next($request);
    }
}
