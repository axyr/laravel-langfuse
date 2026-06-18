<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\ObservationResponse;
use Axyr\Langfuse\Enums\ObservationLevel;
use Tests\Fixtures\ObservationFixtures;

it('creates a generation observation from array', function () {
    $observation = ObservationResponse::fromArray(ObservationFixtures::generation());

    expect($observation->id)->toBe('obs-1')
        ->and($observation->type)->toBe('GENERATION')
        ->and($observation->name)->toBe('llm-call')
        ->and($observation->traceId)->toBe('trace-123')
        ->and($observation->level)->toBe(ObservationLevel::DEFAULT)
        ->and($observation->model)->toBe('gpt-4')
        ->and($observation->modelId)->toBe('model-gpt4')
        ->and($observation->input)->toBe(['prompt' => 'Hello'])
        ->and($observation->output)->toBe(['text' => 'Hi there'])
        ->and($observation->usageDetails)->toBe(['input' => 10, 'output' => 5, 'total' => 15])
        ->and($observation->costDetails)->toBe(['input' => 0.001, 'output' => 0.002, 'total' => 0.003])
        ->and($observation->latency)->toBe(2.0)
        ->and($observation->promptName)->toBe('greeting')
        ->and($observation->promptVersion)->toBe(3);
});

it('maps v2 model field groups onto model and modelId', function () {
    $observation = ObservationResponse::fromArray(ObservationFixtures::v2Observation());

    expect($observation->type)->toBe('SPAN')
        ->and($observation->level)->toBe(ObservationLevel::WARNING)
        ->and($observation->statusMessage)->toBe('slow')
        ->and($observation->parentObservationId)->toBe('obs-1')
        ->and($observation->model)->toBe('text-embedding-3')
        ->and($observation->modelId)->toBe('model-emb3');
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
        ->and($observation->promptVersion)->toBeNull();
});

it('returns null level for an unknown level value', function () {
    $observation = ObservationResponse::fromArray(['id' => 'x', 'level' => 'BOGUS']);

    expect($observation->level)->toBeNull();
});
