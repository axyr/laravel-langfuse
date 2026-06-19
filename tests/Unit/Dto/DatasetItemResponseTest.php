<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\DatasetItemResponse;
use Axyr\Langfuse\Enums\DatasetStatus;
use Tests\Fixtures\DatasetItemFixtures;

it('creates a dataset item from array', function () {
    $item = DatasetItemResponse::fromArray(DatasetItemFixtures::datasetItem());

    expect($item->id)->toBe('di-1')
        ->and($item->status)->toBe(DatasetStatus::ACTIVE)
        ->and($item->input)->toBe(['question' => 'What is 2+2?'])
        ->and($item->expectedOutput)->toBe(['answer' => '4'])
        ->and($item->metadata)->toBe(['difficulty' => 'easy'])
        ->and($item->sourceTraceId)->toBeNull()
        ->and($item->datasetId)->toBe('ds-1')
        ->and($item->datasetName)->toBe('qa-eval')
        ->and($item->createdAt)->toBe('2024-05-01T10:00:00.000Z');
});

it('handles missing keys gracefully', function () {
    $item = DatasetItemResponse::fromArray([]);

    expect($item->id)->toBe('')
        ->and($item->status)->toBeNull()
        ->and($item->input)->toBeNull()
        ->and($item->datasetId)->toBe('')
        ->and($item->datasetName)->toBe('');
});

it('returns null status for an unknown value', function () {
    $item = DatasetItemResponse::fromArray(['id' => 'x', 'status' => 'BOGUS']);

    expect($item->status)->toBeNull();
});
