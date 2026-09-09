<?php

declare(strict_types=1);

use Axyr\Langfuse\Contracts\EventBatcherInterface;
use Axyr\Langfuse\Contracts\TraceContextResolverInterface;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\LangfuseFacade;
use Axyr\Langfuse\Testing\RecordingEventBatcher;
use Axyr\Langfuse\Tracing\AuthTraceContextResolver;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Log;

function recordTraces(): RecordingEventBatcher
{
    config(['langfuse.public_key' => 'pk', 'langfuse.secret_key' => 'sk']);

    $batcher = new RecordingEventBatcher();
    app()->instance(EventBatcherInterface::class, $batcher);

    return $batcher;
}

it('binds the auth resolver as the default', function () {
    expect($this->app->make(TraceContextResolverInterface::class))->toBeInstanceOf(AuthTraceContextResolver::class)
        ->and($this->app->make(TraceContextResolverInterface::class))->toBe($this->app->make(TraceContextResolverInterface::class));
});

it('tags traces with the authenticated user', function () {
    $batcher = recordTraces();
    $this->actingAs(new GenericUser(['id' => 42]));

    LangfuseFacade::trace(new TraceBody(id: 'trace-1'));

    expect($batcher->events()[0]->toArray()['body']['userId'])->toBe('42');
});

it('leaves guests without a userId', function () {
    $batcher = recordTraces();

    LangfuseFacade::trace(new TraceBody(id: 'trace-1'));

    expect($batcher->events()[0]->toArray()['body'])->not->toHaveKey('userId');
});

it('uses a custom resolver bound by the application', function () {
    $batcher = recordTraces();
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

    LangfuseFacade::trace(new TraceBody(id: 'trace-1'));

    $body = $batcher->events()[0]->toArray()['body'];

    expect($body['userId'])->toBe('tenant-7:user-3')
        ->and($body['sessionId'])->toBe('ticket-9');
});

it('still creates the trace when the resolver throws', function () {
    $batcher = recordTraces();
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

    LangfuseFacade::trace(new TraceBody(id: 'trace-1'));

    expect($batcher->events())->toHaveCount(1)
        ->and($batcher->events()[0]->toArray()['body'])->not->toHaveKey('userId');
});

it('respects the user tracing switch', function () {
    config(['langfuse.user_tracing' => false]);
    $this->app->forgetInstance(\Axyr\Langfuse\Config\LangfuseConfig::class);
    $this->app->forgetInstance(\Axyr\Langfuse\Contracts\LangfuseClientInterface::class);
    $batcher = recordTraces();
    $this->actingAs(new GenericUser(['id' => 42]));

    LangfuseFacade::trace(new TraceBody(id: 'trace-1'));

    expect($batcher->events()[0]->toArray()['body'])->not->toHaveKey('userId');
});
