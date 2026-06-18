<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\DatasetResponse;
use Tests\Fixtures\DatasetFixtures;

it('creates a dataset from array', function () {
    $dataset = DatasetResponse::fromArray(DatasetFixtures::dataset());

    expect($dataset->id)->toBe('ds-1')
        ->and($dataset->name)->toBe('qa-eval')
        ->and($dataset->description)->toBe('QA evaluation set')
        ->and($dataset->metadata)->toBe(['owner' => 'team-a'])
        ->and($dataset->inputSchema)->toBeNull()
        ->and($dataset->expectedOutputSchema)->toBeNull()
        ->and($dataset->projectId)->toBe('proj-1')
        ->and($dataset->createdAt)->toBe('2024-05-01T10:00:00.000Z')
        ->and($dataset->updatedAt)->toBe('2024-05-01T10:00:00.000Z');
});

it('handles missing keys gracefully', function () {
    $dataset = DatasetResponse::fromArray([]);

    expect($dataset->id)->toBe('')
        ->and($dataset->name)->toBe('')
        ->and($dataset->projectId)->toBe('')
        ->and($dataset->description)->toBeNull()
        ->and($dataset->metadata)->toBeNull();
});
