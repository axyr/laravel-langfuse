<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\DatasetItemListResponse;
use Axyr\Langfuse\Dto\DatasetItemResponse;
use Axyr\Langfuse\Dto\PromptListMeta;
use Axyr\Langfuse\Enums\DatasetStatus;
use Tests\Fixtures\DatasetItemFixtures;

it('creates a list response from array', function () {
    $response = DatasetItemListResponse::fromArray(DatasetItemFixtures::datasetItemList());

    expect($response->data)->toHaveCount(2)
        ->and($response->data[0])->toBeInstanceOf(DatasetItemResponse::class)
        ->and($response->data[0]->id)->toBe('di-1')
        ->and($response->data[1]->status)->toBe(DatasetStatus::ARCHIVED)
        ->and($response->meta)->toBeInstanceOf(PromptListMeta::class)
        ->and($response->meta->totalItems)->toBe(2);
});

it('handles empty data and missing keys gracefully', function () {
    $response = DatasetItemListResponse::fromArray([]);

    expect($response->data)->toBeEmpty()
        ->and($response->meta->totalItems)->toBe(0)
        ->and($response->meta->page)->toBe(1);
});
