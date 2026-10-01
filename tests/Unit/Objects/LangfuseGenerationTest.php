<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Dto\Usage;
use Axyr\Langfuse\Enums\ObservationLevel;
use Axyr\Langfuse\Enums\ObservationType;
use Axyr\Langfuse\Objects\LangfuseGeneration;
use Axyr\Langfuse\Objects\OpenObservationRegistry;
use Axyr\Langfuse\Testing\RecordingEventBatcher;
use Axyr\Langfuse\Tracing\TraceContext;
use Illuminate\Support\Facades\Log;

function generationOn(RecordingEventBatcher $batcher, ?GenerationBody $body = null, ?OpenObservationRegistry $registry = null): LangfuseGeneration
{
    $context = new TraceContext(new TraceBody(id: 'trace-1'));

    return new LangfuseGeneration(
        body: ($body ?? new GenerationBody(id: 'gen-1'))->withTraceId($context->traceId()),
        batcher: $batcher,
        context: $context,
        registry: $registry,
    );
}

it('sends nothing when the generation is created', function () {
    $batcher = new RecordingEventBatcher();

    generationOn($batcher, new GenerationBody(id: 'gen-1', model: 'gpt-4'));

    expect($batcher->observations())->toBeEmpty();
});

it('exposes id and trace id', function () {
    $batcher = new RecordingEventBatcher();
    $generation = generationOn($batcher);

    expect($generation->getId())->toBe((new GenerationBody(id: 'gen-1'))->id)
        ->and($generation->getTraceId())->toMatch('/^[0-9a-f]{32}$/');
});

it('stamps a start time at creation', function () {
    $generation = generationOn(new RecordingEventBatcher());

    expect($generation->getBody()->startTime)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/');
});

it('exports one completed observation on end', function () {
    $batcher = new RecordingEventBatcher();
    $usage = new Usage(input: 10, output: 20, total: 30);
    $generation = generationOn($batcher, new GenerationBody(
        id: 'gen-1',
        name: 'chat',
        model: 'gpt-4',
        startTime: '2024-01-01T00:00:00Z',
    ));

    $generation->end(
        endTime: '2024-01-01T00:00:02Z',
        output: 'answer',
        usage: $usage,
        statusMessage: 'stop',
    );

    expect($batcher->observations())->toHaveCount(1);

    $exported = $batcher->observations()[0];

    expect($exported->type())->toBe(ObservationType::Generation)
        ->and($exported->name())->toBe('chat')
        ->and($exported->startTime)->toBe('2024-01-01T00:00:00Z')
        ->and($exported->endTime)->toBe('2024-01-01T00:00:02Z')
        ->and($exported->body->output)->toBe('answer')
        ->and($exported->body->usage)->toBe($usage)
        ->and($exported->body->statusMessage)->toBe('stop')
        ->and($exported->body->model)->toBe('gpt-4');
});

it('records an error level on end', function () {
    $batcher = new RecordingEventBatcher();
    $generation = generationOn($batcher);

    $generation->end(statusMessage: 'boom', level: ObservationLevel::ERROR);

    expect($batcher->observations()[0]->level())->toBe(ObservationLevel::ERROR)
        ->and($batcher->observations()[0]->statusMessage())->toBe('boom');
});

it('warns and does nothing when a generation ends twice', function () {
    Log::shouldReceive('warning')->once()->with('Langfuse generation ended twice', Mockery::any());

    $batcher = new RecordingEventBatcher();
    $generation = generationOn($batcher);

    $generation->end();
    $generation->end();

    expect($batcher->observations())->toHaveCount(1);
});

it('defaults the end time to now', function () {
    $batcher = new RecordingEventBatcher();

    generationOn($batcher)->end();

    expect($batcher->observations()[0]->endTime)
        ->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/');
});

it('registers itself and unregisters when it ends', function () {
    $registry = new OpenObservationRegistry();
    $batcher = new RecordingEventBatcher();

    $generation = generationOn($batcher, null, $registry);

    expect($registry->count())->toBe(1);

    $generation->end();

    expect($registry->count())->toBe(0)
        ->and($generation->hasEnded())->toBeTrue();
});

it('marks a generation ended by shutdown with a warning level', function () {
    $batcher = new RecordingEventBatcher();

    generationOn($batcher)->endOnShutdown();

    expect($batcher->observations()[0]->body->level)->toBe(ObservationLevel::WARNING)
        ->and($batcher->observations()[0]->body->statusMessage)->toBe('ended by shutdown');
});
