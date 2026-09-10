<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\CursorMeta;
use Axyr\Langfuse\Dto\ScoreListResponse;
use Axyr\Langfuse\Dto\ScoreResponse;
use Tests\Fixtures\ScoreFixtures;

it('creates a list response with cursor meta from array', function () {
    $response = ScoreListResponse::fromArray(ScoreFixtures::scoreList());

    expect($response->data)->toHaveCount(2)
        ->and($response->data[0])->toBeInstanceOf(ScoreResponse::class)
        ->and($response->data[0]->id)->toBe('score-abc')
        ->and($response->data[1]->id)->toBe('score-cat')
        ->and($response->meta)->toBeInstanceOf(CursorMeta::class)
        ->and($response->meta->limit)->toBe(50)
        ->and($response->meta->cursor)->toBe('eyJpZCI6InNjb3JlLWNhdCJ9')
        ->and($response->meta->hasMore())->toBeTrue();
});

it('has no cursor on the last page', function () {
    $response = ScoreListResponse::fromArray(ScoreFixtures::lastScorePage());

    expect($response->data)->toHaveCount(1)
        ->and($response->meta->cursor)->toBeNull()
        ->and($response->meta->hasMore())->toBeFalse();
});

it('handles empty data', function () {
    $response = ScoreListResponse::fromArray(['data' => [], 'meta' => ['limit' => 50]]);

    expect($response->data)->toBeEmpty()
        ->and($response->meta->limit)->toBe(50);
});

it('handles missing keys gracefully', function () {
    $response = ScoreListResponse::fromArray([]);

    expect($response->data)->toBeEmpty()
        ->and($response->meta->limit)->toBeNull()
        ->and($response->meta->cursor)->toBeNull();
});
