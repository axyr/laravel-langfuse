<?php

declare(strict_types=1);

use Axyr\Langfuse\Cache\PromptCache;
use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\EventBatcherInterface;
use Axyr\Langfuse\Contracts\PromptApiClientInterface;
use Axyr\Langfuse\Contracts\ScoreApiClientInterface;
use Axyr\Langfuse\Dto\IngestionEvent;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Enums\EventType;
use Axyr\Langfuse\LangfuseClient;
use Axyr\Langfuse\Objects\LangfuseTrace;
use Axyr\Langfuse\Prompt\PromptManager;

function createClient(EventBatcherInterface $batcher, ?LangfuseConfig $config = null): LangfuseClient
{
    $config ??= new LangfuseConfig(publicKey: 'pk', secretKey: 'sk');
    $promptApiClient = Mockery::mock(PromptApiClientInterface::class);
    $promptManager = new PromptManager(
        $promptApiClient,
        new PromptCache(),
    );

    return new LangfuseClient(
        $batcher,
        $config,
        $promptManager,
        Mockery::mock(ScoreApiClientInterface::class),
        $promptApiClient,
    );
}

it('creates a trace and returns LangfuseTrace', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('enqueue')->once(); // trace-create

    $client = createClient($batcher);

    $trace = $client->trace(new TraceBody(id: 'trace-1', name: 'test'));

    expect($trace)->toBeInstanceOf(LangfuseTrace::class)
        ->and($trace->getId())->toBe('trace-1');
});

it('creates a trace with auto-generated id', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('enqueue')->once();

    $client = createClient($batcher);

    $trace = $client->trace(new TraceBody(name: 'test'));

    expect($trace)->toBeInstanceOf(LangfuseTrace::class)
        ->and($trace->getId())->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

it('enqueues score event', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('enqueue')
        ->once()
        ->with(Mockery::on(function (IngestionEvent $event) {
            return $event->type === EventType::ScoreCreate
                && $event->body instanceof ScoreBody
                && $event->body->traceId === 'trace-1'
                && $event->body->name === 'accuracy';
        }));

    $client = createClient($batcher);

    $client->score(new ScoreBody(
        name: 'accuracy',
        id: 'score-1',
        traceId: 'trace-1',
        value: 0.95,
    ));
});

it('delegates flush to batcher', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('flush')->once();

    $client = createClient($batcher);

    $client->flush();
});

it('stores and returns current trace', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('enqueue')->once();

    $client = createClient($batcher);

    expect($client->currentTrace())->toBeNull();

    $trace = $client->trace(new TraceBody(name: 'test'));
    $client->setCurrentTrace($trace);

    expect($client->currentTrace())->toBe($trace);
});

it('reports enabled state from config', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);

    $enabledClient = createClient(
        $batcher,
        new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', enabled: true),
    );

    $disabledClient = createClient(
        $batcher,
        new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', enabled: false),
    );

    expect($enabledClient->isEnabled())->toBeTrue()
        ->and($disabledClient->isEnabled())->toBeFalse();
});
