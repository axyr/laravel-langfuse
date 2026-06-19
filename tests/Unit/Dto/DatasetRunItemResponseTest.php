<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\DatasetRunItemListResponse;
use Axyr\Langfuse\Dto\DatasetRunItemResponse;
use Axyr\Langfuse\Dto\PromptListMeta;
use Tests\Fixtures\DatasetRunFixtures;

it('creates a dataset run item from array', function () {
    $item = DatasetRunItemResponse::fromArray(DatasetRunFixtures::datasetRunItem());

    expect($item->id)->toBe('dri-1')
        ->and($item->datasetRunId)->toBe('dr-1')
        ->and($item->datasetRunName)->toBe('run-2024-05')
        ->and($item->datasetItemId)->toBe('di-1')
        ->and($item->traceId)->toBe('trace-abc')
        ->and($item->observationId)->toBeNull();
});

it('handles missing keys gracefully', function () {
    $item = DatasetRunItemResponse::fromArray([]);

    expect($item->id)->toBe('')
        ->and($item->datasetItemId)->toBe('')
        ->and($item->traceId)->toBe('')
        ->and($item->observationId)->toBeNull();
});

it('creates a run item list from array', function () {
    $response = DatasetRunItemListResponse::fromArray(DatasetRunFixtures::datasetRunItemList());

    expect($response->data)->toHaveCount(1)
        ->and($response->data[0])->toBeInstanceOf(DatasetRunItemResponse::class)
        ->and($response->data[0]->id)->toBe('dri-1')
        ->and($response->meta)->toBeInstanceOf(PromptListMeta::class)
        ->and($response->meta->totalItems)->toBe(1);
});
