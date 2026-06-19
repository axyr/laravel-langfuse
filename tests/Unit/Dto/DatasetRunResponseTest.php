<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\DatasetRunItemResponse;
use Axyr\Langfuse\Dto\DatasetRunListResponse;
use Axyr\Langfuse\Dto\DatasetRunResponse;
use Axyr\Langfuse\Dto\DatasetRunWithItemsResponse;
use Axyr\Langfuse\Dto\PromptListMeta;
use Tests\Fixtures\DatasetRunFixtures;

it('creates a dataset run from array', function () {
    $run = DatasetRunResponse::fromArray(DatasetRunFixtures::datasetRun());

    expect($run->id)->toBe('dr-1')
        ->and($run->name)->toBe('run-2024-05')
        ->and($run->description)->toBe('first eval run')
        ->and($run->metadata)->toBe(['model' => 'gpt-4'])
        ->and($run->datasetId)->toBe('ds-1')
        ->and($run->datasetName)->toBe('qa-eval');
});

it('handles missing keys gracefully', function () {
    $run = DatasetRunResponse::fromArray([]);

    expect($run->id)->toBe('')
        ->and($run->name)->toBe('')
        ->and($run->description)->toBeNull()
        ->and($run->metadata)->toBeNull();
});

it('creates a run with items, composing the run and its items', function () {
    $withItems = DatasetRunWithItemsResponse::fromArray(DatasetRunFixtures::datasetRunWithItems());

    expect($withItems->run)->toBeInstanceOf(DatasetRunResponse::class)
        ->and($withItems->run->name)->toBe('run-2024-05')
        ->and($withItems->datasetRunItems)->toHaveCount(1)
        ->and($withItems->datasetRunItems[0])->toBeInstanceOf(DatasetRunItemResponse::class)
        ->and($withItems->datasetRunItems[0]->id)->toBe('dri-1');
});

it('creates a run list from array', function () {
    $response = DatasetRunListResponse::fromArray(DatasetRunFixtures::datasetRunList());

    expect($response->data)->toHaveCount(1)
        ->and($response->data[0])->toBeInstanceOf(DatasetRunResponse::class)
        ->and($response->data[0]->id)->toBe('dr-1')
        ->and($response->meta)->toBeInstanceOf(PromptListMeta::class)
        ->and($response->meta->totalItems)->toBe(1);
});

it('handles an empty run list', function () {
    $response = DatasetRunListResponse::fromArray([]);

    expect($response->data)->toBeEmpty()
        ->and($response->meta->totalItems)->toBe(0);
});
