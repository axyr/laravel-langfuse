<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\ScoreResponse;
use Axyr\Langfuse\Dto\ScoreSubject;
use Tests\Fixtures\ScoreFixtures;

it('creates a numeric score from array', function () {
    $score = ScoreResponse::fromArray(ScoreFixtures::numericScore());

    expect($score->id)->toBe('score-abc')
        ->and($score->projectId)->toBe('project-1')
        ->and($score->dataType)->toBe('NUMERIC')
        ->and($score->name)->toBe('accuracy')
        ->and($score->value)->toBe(0.95)
        ->and($score->source)->toBe('API')
        ->and($score->comment)->toBe('looks good')
        ->and($score->environment)->toBe('production')
        ->and($score->metadata)->toBe(['reviewer' => 'qa'])
        ->and($score->subject)->toBeInstanceOf(ScoreSubject::class)
        ->and($score->subject->kind)->toBe('observation');
});

it('keeps a categorical value as a string, without a stringValue field', function () {
    $score = ScoreResponse::fromArray(ScoreFixtures::categoricalScore());

    expect($score->dataType)->toBe('CATEGORICAL')
        ->and($score->value)->toBe('positive')
        ->and($score)->not->toHaveProperty('stringValue');
});

it('keeps a boolean value as a real boolean', function () {
    $score = ScoreResponse::fromArray(ScoreFixtures::booleanScore());

    expect($score->dataType)->toBe('BOOLEAN')
        ->and($score->value)->toBeTrue()
        ->and($score->authorUserId)->toBe('user-9')
        ->and($score->queueId)->toBe('queue-1');
});

it('coerces integer values to float', function () {
    expect(ScoreResponse::fromArray(['id' => 'x', 'value' => 3])->value)->toBe(3.0);
});

it('resolves what an observation score is attached to', function () {
    $score = ScoreResponse::fromArray(ScoreFixtures::numericScore());

    expect($score->observationId())->toBe('0192f1b42c7e7a1b')
        ->and($score->traceId())->toBe('0192f1b42c7e7a1b8d3e9f0a1b2c3d4e')
        ->and($score->sessionId())->toBeNull()
        ->and($score->experimentId())->toBeNull();
});

it('resolves what a trace score is attached to', function () {
    $score = ScoreResponse::fromArray(ScoreFixtures::booleanScore());

    expect($score->traceId())->toBe('0192f1b42c7e7a1b8d3e9f0a1b2c3d4e')
        ->and($score->observationId())->toBeNull();
});

it('resolves what an experiment score is attached to', function () {
    $score = ScoreResponse::fromArray(ScoreFixtures::categoricalScore());

    expect($score->experimentId())->toBe('experiment-789')
        ->and($score->traceId())->toBeNull()
        ->and($score->observationId())->toBeNull();
});

it('resolves what a session score is attached to', function () {
    $score = ScoreResponse::fromArray([
        'id' => 's',
        'subject' => ['kind' => 'session', 'id' => 'session-1'],
    ]);

    expect($score->sessionId())->toBe('session-1')
        ->and($score->traceId())->toBeNull();
});

it('handles missing keys gracefully', function () {
    $score = ScoreResponse::fromArray([]);

    expect($score->id)->toBe('')
        ->and($score->name)->toBe('')
        ->and($score->dataType)->toBe('')
        ->and($score->value)->toBeNull()
        ->and($score->subject)->toBeNull()
        ->and($score->metadata)->toBeNull()
        ->and($score->traceId())->toBeNull();
});

it('preserves an unmapped dataType such as CORRECTION', function () {
    $score = ScoreResponse::fromArray([
        'id' => 'c',
        'dataType' => 'CORRECTION',
        'value' => 'fixed text',
    ]);

    expect($score->dataType)->toBe('CORRECTION')
        ->and($score->value)->toBe('fixed text');
});

it('creates a subject with defaults for missing fields', function () {
    $subject = ScoreSubject::fromArray([]);

    expect($subject->kind)->toBe('')
        ->and($subject->id)->toBe('')
        ->and($subject->traceId)->toBeNull();
});
