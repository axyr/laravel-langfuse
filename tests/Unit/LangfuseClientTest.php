<?php

declare(strict_types=1);

use Langfuse\Config\LangfuseConfig;
use Langfuse\Contracts\EventBatcherInterface;
use Langfuse\Dto\IngestionEvent;
use Langfuse\Dto\ScoreBody;
use Langfuse\Dto\TraceBody;
use Langfuse\Enums\EventType;
use Langfuse\LangfuseClient;
use Langfuse\Objects\LangfuseTrace;

it('creates a trace and returns LangfuseTrace', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('enqueue')->once(); // trace-create
    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk');

    $client = new LangfuseClient($batcher, $config);

    $trace = $client->trace(new TraceBody(id: 'trace-1', name: 'test'));

    expect($trace)->toBeInstanceOf(LangfuseTrace::class)
        ->and($trace->getId())->toBe('trace-1');
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

    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk');
    $client = new LangfuseClient($batcher, $config);

    $client->score(new ScoreBody(
        id: 'score-1',
        traceId: 'trace-1',
        name: 'accuracy',
        value: 0.95,
    ));
});

it('delegates flush to batcher', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('flush')->once();

    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk');
    $client = new LangfuseClient($batcher, $config);

    $client->flush();
});

it('reports enabled state from config', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);

    $enabledClient = new LangfuseClient(
        $batcher,
        new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', enabled: true),
    );

    $disabledClient = new LangfuseClient(
        $batcher,
        new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', enabled: false),
    );

    expect($enabledClient->isEnabled())->toBeTrue()
        ->and($disabledClient->isEnabled())->toBeFalse();
});
