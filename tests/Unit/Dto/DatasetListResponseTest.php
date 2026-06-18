<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\DatasetListResponse;
use Axyr\Langfuse\Dto\DatasetResponse;
use Axyr\Langfuse\Dto\PromptListMeta;
use Tests\Fixtures\DatasetFixtures;

it('creates a list response from array', function () {
    $response = DatasetListResponse::fromArray(DatasetFixtures::datasetList());

    expect($response->data)->toHaveCount(2)
        ->and($response->data[0])->toBeInstanceOf(DatasetResponse::class)
        ->and($response->data[0]->name)->toBe('qa-eval')
        ->and($response->data[1]->name)->toBe('regression')
        ->and($response->meta)->toBeInstanceOf(PromptListMeta::class)
        ->and($response->meta->totalItems)->toBe(2)
        ->and($response->meta->limit)->toBe(50);
});

it('handles empty data and missing keys gracefully', function () {
    $response = DatasetListResponse::fromArray([]);

    expect($response->data)->toBeEmpty()
        ->and($response->meta->totalItems)->toBe(0)
        ->and($response->meta->page)->toBe(1);
});
