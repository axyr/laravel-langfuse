<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\ScoreResponse;
use Axyr\Langfuse\Dto\ScoreTraceData;
use Tests\Fixtures\ScoreFixtures;

it('creates a numeric score from array', function () {
    $score = ScoreResponse::fromArray(ScoreFixtures::numericScore());

    expect($score->id)->toBe('score-abc')
        ->and($score->dataType)->toBe('NUMERIC')
        ->and($score->name)->toBe('accuracy')
        ->and($score->value)->toBe(0.95)
        ->and($score->stringValue)->toBeNull()
        ->and($score->source)->toBe('API')
        ->and($score->traceId)->toBe('trace-123')
        ->and($score->observationId)->toBe('obs-1')
        ->and($score->comment)->toBe('looks good')
        ->and($score->environment)->toBe('production')
        ->and($score->metadata)->toBe(['reviewer' => 'qa'])
        ->and($score->trace)->toBeNull();
});

it('creates a categorical score with trace data from array', function () {
    $score = ScoreResponse::fromArray(ScoreFixtures::categoricalScore());

    expect($score->dataType)->toBe('CATEGORICAL')
        ->and($score->value)->toBe(1.0)
        ->and($score->stringValue)->toBe('positive')
        ->and($score->datasetRunId)->toBe('run-789')
        ->and($score->trace)->toBeInstanceOf(ScoreTraceData::class)
        ->and($score->trace->userId)->toBe('user-1')
        ->and($score->trace->tags)->toBe(['prod', 'v2'])
        ->and($score->trace->sessionId)->toBe('sess-1');
});

it('coerces integer values to float', function () {
    $score = ScoreResponse::fromArray(['id' => 'x', 'value' => 3]);

    expect($score->value)->toBe(3.0);
});

it('handles missing keys gracefully', function () {
    $score = ScoreResponse::fromArray([]);

    expect($score->id)->toBe('')
        ->and($score->name)->toBe('')
        ->and($score->dataType)->toBe('')
        ->and($score->value)->toBeNull()
        ->and($score->traceId)->toBeNull()
        ->and($score->metadata)->toBeNull()
        ->and($score->trace)->toBeNull();
});

it('preserves an unmapped dataType such as CORRECTION', function () {
    $score = ScoreResponse::fromArray([
        'id' => 'c',
        'dataType' => 'CORRECTION',
        'value' => 0,
        'stringValue' => 'fixed text',
    ]);

    expect($score->dataType)->toBe('CORRECTION')
        ->and($score->stringValue)->toBe('fixed text');
});

it('creates trace data with defaults for missing fields', function () {
    $trace = ScoreTraceData::fromArray([]);

    expect($trace->userId)->toBeNull()
        ->and($trace->tags)->toBe([])
        ->and($trace->environment)->toBeNull()
        ->and($trace->sessionId)->toBeNull();
});
