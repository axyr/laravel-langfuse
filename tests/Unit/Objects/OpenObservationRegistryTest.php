<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Objects\LangfuseTrace;
use Axyr\Langfuse\Objects\OpenObservationRegistry;
use Axyr\Langfuse\Testing\RecordingEventBatcher;

it('starts empty', function () {
    expect((new OpenObservationRegistry())->count())->toBe(0)
        ->and((new OpenObservationRegistry())->all())->toBeEmpty();
});

it('tracks open observations and remembers everything registered', function () {
    $registry = new OpenObservationRegistry();
    $batcher = new RecordingEventBatcher();

    $trace = new LangfuseTrace(new TraceBody(id: 'trace-1'), $batcher, $registry);
    $span = $trace->span(new SpanBody(name: 'work'));

    expect($registry->count())->toBe(2)
        ->and($registry->all())->toHaveCount(2);

    $span->end();

    expect($registry->count())->toBe(1)
        ->and($registry->open())->toBe([$trace])
        ->and($registry->all())->toHaveCount(2);
});

it('ends children before their trace', function () {
    $registry = new OpenObservationRegistry();
    $batcher = new RecordingEventBatcher();

    $trace = new LangfuseTrace(new TraceBody(id: 'trace-1', name: 'root'), $batcher, $registry);
    $trace->span(new SpanBody(name: 'work'));
    $trace->generation(new GenerationBody(name: 'gen'));

    $registry->endAll();

    $names = array_map(fn($observation): string => $observation->name(), $batcher->observations());

    expect($names)->toBe(['gen', 'work', 'root'])
        ->and($registry->count())->toBe(0);
});

it('does not end the same observation twice', function () {
    $registry = new OpenObservationRegistry();
    $batcher = new RecordingEventBatcher();

    $trace = new LangfuseTrace(new TraceBody(id: 'trace-1'), $batcher, $registry);
    $trace->end();

    $registry->endAll();

    expect($batcher->observations())->toHaveCount(1);
});

it('can be reset', function () {
    $registry = new OpenObservationRegistry();
    $batcher = new RecordingEventBatcher();

    new LangfuseTrace(new TraceBody(id: 'trace-1'), $batcher, $registry);

    $registry->reset();

    expect($registry->count())->toBe(0)
        ->and($registry->all())->toBeEmpty();
});
