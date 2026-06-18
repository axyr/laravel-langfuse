<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\PromptListMeta;
use Axyr\Langfuse\Dto\ScoreListResponse;
use Axyr\Langfuse\Dto\ScoreResponse;
use Tests\Fixtures\ScoreFixtures;

it('creates a list response from array', function () {
    $response = ScoreListResponse::fromArray(ScoreFixtures::scoreList());

    expect($response->data)->toHaveCount(2)
        ->and($response->data[0])->toBeInstanceOf(ScoreResponse::class)
        ->and($response->data[0]->id)->toBe('score-abc')
        ->and($response->data[1]->id)->toBe('score-cat')
        ->and($response->meta)->toBeInstanceOf(PromptListMeta::class)
        ->and($response->meta->totalItems)->toBe(2)
        ->and($response->meta->page)->toBe(1)
        ->and($response->meta->limit)->toBe(50);
});

it('handles empty data', function () {
    $response = ScoreListResponse::fromArray([
        'data' => [],
        'meta' => ['totalItems' => 0, 'totalPages' => 0, 'page' => 1, 'limit' => 50],
    ]);

    expect($response->data)->toBeEmpty()
        ->and($response->meta->totalItems)->toBe(0);
});

it('handles missing keys gracefully', function () {
    $response = ScoreListResponse::fromArray([]);

    expect($response->data)->toBeEmpty()
        ->and($response->meta->totalItems)->toBe(0)
        ->and($response->meta->page)->toBe(1);
});
