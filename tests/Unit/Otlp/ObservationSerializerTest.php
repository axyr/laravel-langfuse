<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\EventBody;
use Axyr\Langfuse\Dto\ExperimentContext;
use Axyr\Langfuse\Dto\ExperimentItemContext;
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\Otlp\OtlpSpan;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Dto\Timestamp;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Dto\Usage;
use Axyr\Langfuse\Enums\ObservationLevel;
use Axyr\Langfuse\Enums\ObservationType;
use Axyr\Langfuse\Otlp\ObservationSerializer;
use Axyr\Langfuse\Tracing\CompletedObservation;
use Axyr\Langfuse\Tracing\TraceContext;
use Illuminate\Support\Facades\Log;

/**
 * @return array<string, mixed>
 */
function attributesOf(OtlpSpan $span): array
{
    $attributes = [];

    foreach ($span->attributes as $attribute) {
        $attributes[$attribute->key] = array_values($attribute->value)[0];
    }

    return $attributes;
}

function contextFor(?TraceBody $body = null): TraceContext
{
    return new TraceContext($body ?? new TraceBody(id: 'trace-1', name: 'checkout'));
}

it('maps the root observation onto a parentless span', function () {
    $context = contextFor(new TraceBody(
        id: 'trace-1',
        name: 'checkout',
        input: 'question',
        output: 'answer',
        timestamp: '2024-01-01T00:00:00Z',
    ));

    $span = (new ObservationSerializer())->toSpan(
        CompletedObservation::root($context, '2024-01-01T00:00:05Z'),
    );

    expect($span->traceId)->toBe($context->traceId())
        ->and($span->spanId)->toBe($context->rootObservationId())
        ->and($span->parentSpanId)->toBeNull()
        ->and($span->name)->toBe('checkout')
        ->and($span->startTimeUnixNano)->toBe(Timestamp::toUnixNano('2024-01-01T00:00:00Z'))
        ->and($span->endTimeUnixNano)->toBe(Timestamp::toUnixNano('2024-01-01T00:00:05Z'))
        ->and(attributesOf($span))->toMatchArray([
            'langfuse.observation.type' => 'span',
            'langfuse.trace.name' => 'checkout',
            'langfuse.observation.input' => 'question',
            'langfuse.observation.output' => 'answer',
        ]);
});

it('never emits the deprecated trace input and output attributes', function () {
    $context = contextFor(new TraceBody(id: 'trace-1', input: 'question', output: 'answer'));

    $attributes = attributesOf((new ObservationSerializer())->toSpan(
        CompletedObservation::root($context, '2024-01-01T00:00:05Z'),
    ));

    expect($attributes)->not->toHaveKey('langfuse.trace.input')
        ->and($attributes)->not->toHaveKey('langfuse.trace.output');
});

it('maps every trace-level field onto its attribute', function () {
    $context = contextFor(new TraceBody(
        id: 'trace-1',
        name: 'checkout',
        userId: 'user-1',
        sessionId: 'session-1',
        release: '1.2.3',
        version: 'v9',
        metadata: ['source' => 'test', 'retries' => 2],
        tags: ['alpha', 'beta'],
        public: true,
        environment: 'production',
    ));

    $attributes = attributesOf((new ObservationSerializer())->toSpan(
        CompletedObservation::root($context, '2024-01-01T00:00:05Z'),
    ));

    expect($attributes)->toMatchArray([
        'langfuse.trace.name' => 'checkout',
        'langfuse.user.id' => 'user-1',
        'langfuse.session.id' => 'session-1',
        'langfuse.release' => '1.2.3',
        'langfuse.version' => 'v9',
        'langfuse.trace.public' => true,
        'langfuse.environment' => 'production',
        'langfuse.trace.metadata.source' => 'test',
        'langfuse.trace.metadata.retries' => 2,
    ]);
});

it('emits tags as an otlp array value', function () {
    $context = contextFor(new TraceBody(id: 'trace-1', tags: ['alpha', 'beta']));

    $span = (new ObservationSerializer())->toSpan(CompletedObservation::root($context, '2024-01-01T00:00:05Z'));

    $tags = array_values(array_filter(
        $span->attributes,
        fn($attribute): bool => $attribute->key === 'langfuse.trace.tags',
    ));

    expect($tags[0]->value)->toBe([
        'arrayValue' => ['values' => [['stringValue' => 'alpha'], ['stringValue' => 'beta']]],
    ]);
});

it('copies trace-level attributes onto every child span', function () {
    $context = contextFor(new TraceBody(
        id: 'trace-1',
        name: 'checkout',
        userId: 'user-1',
        sessionId: 'session-1',
        tags: ['alpha'],
    ));

    $body = (new SpanBody(name: 'work', startTime: '2024-01-01T00:00:01Z', endTime: '2024-01-01T00:00:02Z'))
        ->withContext($context->traceId(), $context->rootObservationId());

    $span = (new ObservationSerializer())->toSpan(CompletedObservation::span($body, $context));

    expect($span->parentSpanId)->toBe($context->rootObservationId())
        ->and(attributesOf($span))->toMatchArray([
            'langfuse.trace.name' => 'checkout',
            'langfuse.user.id' => 'user-1',
            'langfuse.session.id' => 'session-1',
            'langfuse.observation.type' => 'span',
        ]);
});

it('picks up a trace attribute set after the child ended', function () {
    $context = contextFor();
    $body = (new SpanBody(name: 'work'))->withContext($context->traceId(), $context->rootObservationId());
    $observation = CompletedObservation::span($body->completed('2024-01-01T00:00:02Z'), $context);

    $context->merge(new TraceBody(userId: 'user-late'));

    expect(attributesOf((new ObservationSerializer())->toSpan($observation)))
        ->toHaveKey('langfuse.user.id', 'user-late');
});

it('lets the observation win over the trace for version and environment', function () {
    $context = contextFor(new TraceBody(id: 'trace-1', version: 'trace-v', environment: 'production'));
    $body = (new SpanBody(name: 'work', version: 'span-v', environment: 'staging'))
        ->withContext($context->traceId(), $context->rootObservationId());

    expect(attributesOf((new ObservationSerializer())->toSpan(CompletedObservation::span($body, $context))))
        ->toMatchArray([
            'langfuse.version' => 'span-v',
            'langfuse.environment' => 'staging',
        ]);
});

it('maps the span type override', function () {
    $context = contextFor();
    $body = (new SpanBody(name: 'search', type: ObservationType::Retriever))
        ->withContext($context->traceId(), $context->rootObservationId());

    expect(attributesOf((new ObservationSerializer())->toSpan(CompletedObservation::span($body, $context))))
        ->toHaveKey('langfuse.observation.type', 'retriever');
});

it('maps every generation field onto its attribute', function () {
    $context = contextFor();
    $body = (new GenerationBody(
        name: 'chat',
        startTime: '2024-01-01T00:00:00Z',
        endTime: '2024-01-01T00:00:02Z',
        completionStartTime: '2024-01-01T00:00:01Z',
        input: 'question',
        output: 'answer',
        metadata: ['provider' => 'openai'],
        model: 'gpt-4',
        modelParameters: ['temperature' => 0.7],
        usage: new Usage(input: 10, output: 20, total: 30, inputCost: 0.1, outputCost: 0.2, totalCost: 0.3),
        promptName: 'movie-critic',
        promptVersion: 7,
    ))->withContext($context->traceId(), $context->rootObservationId());

    $attributes = attributesOf((new ObservationSerializer())->toSpan(CompletedObservation::generation($body, $context)));

    expect($attributes)->toMatchArray([
        'langfuse.observation.type' => 'generation',
        'langfuse.observation.input' => 'question',
        'langfuse.observation.output' => 'answer',
        'langfuse.observation.metadata.provider' => 'openai',
        'langfuse.observation.model.name' => 'gpt-4',
        'langfuse.observation.model.parameters' => '{"temperature":0.7}',
        'langfuse.observation.usage_details' => '{"input":10,"output":20,"total":30}',
        'langfuse.observation.cost_details' => '{"input":0.1,"output":0.2,"total":0.3}',
        'langfuse.observation.prompt.name' => 'movie-critic',
        'langfuse.observation.prompt.version' => 7,
        'langfuse.observation.completion_start_time' => '2024-01-01T00:00:01Z',
    ]);
});

it('merges arbitrary usage and cost keys into the details attributes', function () {
    $context = contextFor();
    $body = (new GenerationBody(
        name: 'chat',
        usage: new Usage(
            input: 10,
            output: 20,
            details: ['cache_read' => 5],
            costDetails: ['cache_read' => 0.01],
        ),
    ))->withContext($context->traceId(), $context->rootObservationId());

    expect(attributesOf((new ObservationSerializer())->toSpan(CompletedObservation::generation($body, $context))))
        ->toMatchArray([
            'langfuse.observation.usage_details' => '{"input":10,"output":20,"cache_read":5}',
            'langfuse.observation.cost_details' => '{"cache_read":0.01}',
        ]);
});

it('omits the usage attributes when there is no usage', function () {
    $context = contextFor();
    $body = (new GenerationBody(name: 'chat'))->withContext($context->traceId(), $context->rootObservationId());

    $attributes = attributesOf((new ObservationSerializer())->toSpan(CompletedObservation::generation($body, $context)));

    expect($attributes)->not->toHaveKey('langfuse.observation.usage_details')
        ->and($attributes)->not->toHaveKey('langfuse.observation.cost_details');
});

it('json encodes non-string input and output', function () {
    $context = contextFor();
    $body = (new SpanBody(name: 'work', input: ['a' => 1], output: ['b' => 2]))
        ->withContext($context->traceId(), $context->rootObservationId());

    expect(attributesOf((new ObservationSerializer())->toSpan(CompletedObservation::span($body, $context))))
        ->toMatchArray([
            'langfuse.observation.input' => '{"a":1}',
            'langfuse.observation.output' => '{"b":2}',
        ]);
});

it('maps the level and the error status', function () {
    $context = contextFor();
    $body = (new SpanBody(name: 'work', level: ObservationLevel::ERROR, statusMessage: 'boom'))
        ->withContext($context->traceId(), $context->rootObservationId());

    $span = (new ObservationSerializer())->toSpan(CompletedObservation::span($body, $context));

    expect($span->statusCode)->toBe(OtlpSpan::STATUS_ERROR)
        ->and($span->statusMessage)->toBe('boom')
        ->and(attributesOf($span))->toMatchArray([
            'langfuse.observation.level' => 'ERROR',
            'langfuse.observation.status_message' => 'boom',
        ]);
});

it('leaves the status unset for a non-error observation', function () {
    $context = contextFor();
    $body = (new SpanBody(name: 'work', level: ObservationLevel::WARNING, statusMessage: 'slow'))
        ->withContext($context->traceId(), $context->rootObservationId());

    $span = (new ObservationSerializer())->toSpan(CompletedObservation::span($body, $context));

    expect($span->statusCode)->toBe(OtlpSpan::STATUS_UNSET)
        ->and($span->statusMessage)->toBe('')
        ->and(attributesOf($span))->toHaveKey('langfuse.observation.level', 'WARNING');
});

it('gives an event equal start and end times', function () {
    $context = contextFor();
    $body = (new EventBody(name: 'checkpoint', startTime: '2024-01-01T00:00:03Z'))
        ->withContext($context->traceId(), $context->rootObservationId());

    $span = (new ObservationSerializer())->toSpan(CompletedObservation::event($body, $context));

    expect($span->startTimeUnixNano)->toBe($span->endTimeUnixNano)
        ->and(attributesOf($span))->toHaveKey('langfuse.observation.type', 'event');
});

it('emits experiment attributes on every span and item attributes on the root only', function () {
    $context = contextFor(new TraceBody(
        id: 'trace-1',
        name: 'eval',
        input: 'question',
        output: 'answer',
        experiment: new ExperimentContext(
            id: 'exp-1',
            name: 'nightly',
            datasetId: 'ds-1',
            description: 'nightly run',
            metadata: ['model' => 'gpt-4'],
        ),
        experimentItem: new ExperimentItemContext(
            itemId: 'item-1',
            expectedOutput: 'answer',
            version: '2024-01-01T00:00:00Z',
            metadata: ['difficulty' => 'hard'],
        ),
    ));

    $serializer = new ObservationSerializer();

    $root = attributesOf($serializer->toSpan(CompletedObservation::root($context, '2024-01-01T00:00:05Z')));

    $childBody = (new SpanBody(name: 'work'))->withContext($context->traceId(), $context->rootObservationId());
    $child = attributesOf($serializer->toSpan(CompletedObservation::span($childBody, $context)));

    expect($root)->toMatchArray([
        'langfuse.experiment.id' => 'exp-1',
        'langfuse.experiment.name' => 'nightly',
        'langfuse.experiment.dataset.id' => 'ds-1',
        'langfuse.experiment.description' => 'nightly run',
        'langfuse.experiment.metadata.model' => 'gpt-4',
        'langfuse.experiment.item.id' => 'item-1',
        'langfuse.experiment.item.root_observation_id' => $context->rootObservationId(),
        'langfuse.experiment.item.expected_output' => 'answer',
        'langfuse.experiment.item.version' => '2024-01-01T00:00:00Z',
        'langfuse.experiment.item.metadata.difficulty' => 'hard',
    ])
        ->and($child)->toMatchArray([
            'langfuse.experiment.id' => 'exp-1',
            'langfuse.experiment.name' => 'nightly',
            'langfuse.experiment.dataset.id' => 'ds-1',
        ])
        ->and($child)->not->toHaveKey('langfuse.experiment.item.id')
        ->and($child)->not->toHaveKey('langfuse.experiment.item.root_observation_id');
});

it('points the experiment item at the root span it is on', function () {
    $context = contextFor(new TraceBody(
        id: 'trace-1',
        experiment: ExperimentContext::named('nightly'),
        experimentItem: new ExperimentItemContext(itemId: 'item-1'),
    ));

    $span = (new ObservationSerializer())->toSpan(CompletedObservation::root($context, '2024-01-01T00:00:05Z'));

    expect(attributesOf($span)['langfuse.experiment.item.root_observation_id'])->toBe($span->spanId);
});

it('substitutes the current time and logs for an unparseable timestamp', function () {
    Log::shouldReceive('warning')->twice()->with('Langfuse timestamp could not be parsed', Mockery::any());

    $context = contextFor(new TraceBody(id: 'trace-1', timestamp: 'not a timestamp'));

    $span = (new ObservationSerializer())->toSpan(CompletedObservation::root($context, 'also not a timestamp'));

    expect($span->startTimeUnixNano)->toMatch('/^\d+$/')
        ->and($span->endTimeUnixNano)->toMatch('/^\d+$/');
});
