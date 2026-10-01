<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Enums\ObservationType;
use Axyr\Langfuse\Testing\RecordingEventBatcher;
use Axyr\Langfuse\Tracing\CompletedObservation;
use Axyr\Langfuse\Tracing\TraceContext;

function recordedSpan(string $name = 'work'): CompletedObservation
{
    $context = new TraceContext(new TraceBody(id: 'trace-1'));
    $body = (new SpanBody(name: $name))->withContext($context->traceId(), $context->rootObservationId());

    return CompletedObservation::span($body->completed('2024-01-01T00:00:01Z'), $context);
}

function recordedGeneration(string $name = 'chat'): CompletedObservation
{
    $context = new TraceContext(new TraceBody(id: 'trace-1'));
    $body = (new GenerationBody(name: $name))->withContext($context->traceId(), $context->rootObservationId());

    return CompletedObservation::generation($body->completed('2024-01-01T00:00:01Z'), $context);
}

it('records enqueued observations', function () {
    $batcher = new RecordingEventBatcher();
    $observation = recordedSpan();

    $batcher->enqueue($observation);

    expect($batcher->observations())->toHaveCount(1)
        ->and($batcher->observations()[0])->toBe($observation);
});

it('records enqueued scores separately', function () {
    $batcher = new RecordingEventBatcher();
    $score = new ScoreBody(name: 'accuracy', value: 1.0);

    $batcher->enqueueScore($score, '2024-01-01T00:00:00Z');

    expect($batcher->scores())->toHaveCount(1)
        ->and($batcher->scores()[0])->toBe($score)
        ->and($batcher->observations())->toBeEmpty();
});

it('counts observations and scores together', function () {
    $batcher = new RecordingEventBatcher();

    expect($batcher->count())->toBe(0);

    $batcher->enqueue(recordedSpan());
    $batcher->enqueueScore(new ScoreBody(name: 'accuracy'));

    expect($batcher->count())->toBe(2);
});

it('filters observations by type', function () {
    $batcher = new RecordingEventBatcher();

    $batcher->enqueue(recordedSpan());
    $batcher->enqueue(recordedGeneration());

    expect($batcher->observationsOfType(ObservationType::Span))->toHaveCount(1)
        ->and($batcher->observationsOfType(ObservationType::Generation))->toHaveCount(1)
        ->and($batcher->observationsOfType(ObservationType::Event))->toHaveCount(0);
});

it('resets both queues', function () {
    $batcher = new RecordingEventBatcher();

    $batcher->enqueue(recordedSpan());
    $batcher->enqueueScore(new ScoreBody(name: 'accuracy'));

    $batcher->reset();

    expect($batcher->observations())->toBeEmpty()
        ->and($batcher->scores())->toBeEmpty()
        ->and($batcher->count())->toBe(0);
});

it('flush is a no-op', function () {
    $batcher = new RecordingEventBatcher();

    $batcher->enqueue(recordedSpan());
    $batcher->flush();

    expect($batcher->observations())->toHaveCount(1);
});
