<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\ObservationResponse;
use Axyr\Langfuse\Enums\ObservationLevel;
use Tests\Fixtures\ObservationFixtures;

it('creates a generation observation from array', function () {
    $observation = ObservationResponse::fromArray(ObservationFixtures::generation());

    expect($observation->id)->toBe('0192f1b42c7e7a1b')
        ->and($observation->type)->toBe('GENERATION')
        ->and($observation->name)->toBe('llm-call')
        ->and($observation->traceId)->toBe('0192f1b42c7e7a1b8d3e9f0a1b2c3d4e')
        ->and($observation->level)->toBe(ObservationLevel::DEFAULT)
        ->and($observation->model)->toBe('gpt-4')
        ->and($observation->modelId)->toBe('model-gpt4')
        ->and($observation->input)->toBe('{"prompt":"Hello"}')
        ->and($observation->output)->toBe('{"text":"Hi there"}')
        ->and($observation->usageDetails)->toBe(['input' => 10, 'output' => 5, 'total' => 15])
        ->and($observation->costDetails)->toBe(['input' => 0.001, 'output' => 0.002, 'total' => 0.003])
        ->and($observation->latency)->toBe(2.0)
        ->and($observation->promptName)->toBe('greeting')
        ->and($observation->promptVersion)->toBe(3);
});

it('maps the v2 field groups that go beyond core and basic', function () {
    $observation = ObservationResponse::fromArray(ObservationFixtures::generation());

    expect($observation->isRootObservation)->toBeFalse()
        ->and($observation->projectId)->toBe('project-1')
        ->and($observation->bookmarked)->toBeFalse()
        ->and($observation->public)->toBeFalse()
        ->and($observation->userId)->toBe('user-1')
        ->and($observation->sessionId)->toBe('session-1')
        ->and($observation->createdAt)->toBe('2024-05-01T12:00:02.100Z')
        ->and($observation->updatedAt)->toBe('2024-05-01T12:00:02.100Z')
        ->and($observation->totalCost)->toBe(0.003)
        ->and($observation->usagePricingTierName)->toBe('standard')
        ->and($observation->promptId)->toBe('prompt-9')
        ->and($observation->timeToFirstToken)->toBe(0.5)
        ->and($observation->traceName)->toBe('checkout')
        ->and($observation->tags)->toBe(['prod', 'v4'])
        ->and($observation->release)->toBe('1.2.3')
        ->and($observation->modelParameters)->toBe(['temperature' => 0.7])
        ->and($observation->version)->toBe('v9');
});

it('reads internalModelId as the model id', function () {
    $observation = ObservationResponse::fromArray(ObservationFixtures::v2Observation());

    expect($observation->type)->toBe('SPAN')
        ->and($observation->level)->toBe(ObservationLevel::WARNING)
        ->and($observation->statusMessage)->toBe('slow')
        ->and($observation->parentObservationId)->toBe('aaaaaaaaaaaaaaaa')
        ->and($observation->model)->toBe('text-embedding-3')
        ->and($observation->modelId)->toBe('model-emb3');
});

it('marks the root observation of a trace', function () {
    $observation = ObservationResponse::fromArray(ObservationFixtures::rootObservation());

    expect($observation->isRootObservation)->toBeTrue()
        ->and($observation->traceName)->toBe('checkout');
});

it('passes a v4 observation type through as a string', function () {
    expect(ObservationResponse::fromArray(['id' => 'x', 'type' => 'RETRIEVER'])->type)->toBe('RETRIEVER');
});

it('handles missing keys gracefully', function () {
    $observation = ObservationResponse::fromArray([]);

    expect($observation->id)->toBe('')
        ->and($observation->type)->toBe('')
        ->and($observation->name)->toBeNull()
        ->and($observation->level)->toBeNull()
        ->and($observation->model)->toBeNull()
        ->and($observation->usageDetails)->toBeNull()
        ->and($observation->latency)->toBeNull()
        ->and($observation->promptVersion)->toBeNull()
        ->and($observation->isRootObservation)->toBeNull()
        ->and($observation->tags)->toBeNull();
});

it('returns null level for an unknown level value', function () {
    $observation = ObservationResponse::fromArray(['id' => 'x', 'level' => 'BOGUS']);

    expect($observation->level)->toBeNull();
});
