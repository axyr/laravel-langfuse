<?php

declare(strict_types=1);

use Axyr\Langfuse\Contracts\EventBatcherInterface;
use Axyr\Langfuse\Contracts\TraceContextResolverInterface;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\LangfuseFacade;
use Axyr\Langfuse\Objects\LangfuseTrace;
use Axyr\Langfuse\Tracing\AuthTraceContextResolver;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Log;

function recordTraces(): void
{
    config(['langfuse.public_key' => 'pk', 'langfuse.secret_key' => 'sk']);

    app()->instance(EventBatcherInterface::class, new \Axyr\Langfuse\Batch\NullEventBatcher());
}

/**
 * @return array<string, mixed>
 */
function traceBodyOf(LangfuseTrace $trace): array
{
    return $trace->getBody()->toArray();
}

it('binds the auth resolver as the default', function () {
    expect($this->app->make(TraceContextResolverInterface::class))->toBeInstanceOf(AuthTraceContextResolver::class)
        ->and($this->app->make(TraceContextResolverInterface::class))->toBe($this->app->make(TraceContextResolverInterface::class));
});

it('tags traces with the authenticated user', function () {
    recordTraces();
    $this->actingAs(new GenericUser(['id' => 42]));

    expect(traceBodyOf(LangfuseFacade::trace(new TraceBody(id: 'trace-1')))['userId'])->toBe('42');
});

it('leaves guests without a userId', function () {
    recordTraces();

    expect(traceBodyOf(LangfuseFacade::trace(new TraceBody(id: 'trace-1'))))->not->toHaveKey('userId');
});

it('uses a custom resolver bound by the application', function () {
    recordTraces();
    $this->app->instance(TraceContextResolverInterface::class, new class () implements TraceContextResolverInterface {
        public function resolveUserId(): ?string
        {
            return 'tenant-7:user-3';
        }

        public function resolveSessionId(): ?string
        {
            return 'ticket-9';
        }
    });

    $body = traceBodyOf(LangfuseFacade::trace(new TraceBody(id: 'trace-1')));

    expect($body['userId'])->toBe('tenant-7:user-3')
        ->and($body['sessionId'])->toBe('ticket-9');
});

it('still creates the trace when the resolver throws', function () {
    recordTraces();
    $this->app->instance(TraceContextResolverInterface::class, new class () implements TraceContextResolverInterface {
        public function resolveUserId(): ?string
        {
            throw new RuntimeException('guard exploded');
        }

        public function resolveSessionId(): ?string
        {
            return null;
        }
    });
    Log::shouldReceive('warning')->once()->with('Langfuse trace context resolution failed', ['message' => 'guard exploded']);

    $trace = LangfuseFacade::trace(new TraceBody(id: 'trace-1'));

    expect($trace->getId())->toBe(\Axyr\Langfuse\Dto\IdGenerator::traceIdFromSeed('trace-1'))
        ->and(traceBodyOf($trace))->not->toHaveKey('userId');
});

it('respects the user tracing switch', function () {
    config(['langfuse.user_tracing' => false]);
    $this->app->forgetInstance(\Axyr\Langfuse\Config\LangfuseConfig::class);
    $this->app->forgetInstance(\Axyr\Langfuse\Contracts\LangfuseClientInterface::class);
    recordTraces();
    $this->actingAs(new GenericUser(['id' => 42]));

    expect(traceBodyOf(LangfuseFacade::trace(new TraceBody(id: 'trace-1'))))->not->toHaveKey('userId');
});
