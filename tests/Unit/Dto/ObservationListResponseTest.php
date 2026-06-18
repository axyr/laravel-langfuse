<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\ObservationListMeta;
use Axyr\Langfuse\Dto\ObservationListResponse;
use Axyr\Langfuse\Dto\ObservationResponse;
use Tests\Fixtures\ObservationFixtures;

it('creates a list response with a cursor from array', function () {
    $response = ObservationListResponse::fromArray(ObservationFixtures::v2List());

    expect($response->data)->toHaveCount(1)
        ->and($response->data[0])->toBeInstanceOf(ObservationResponse::class)
        ->and($response->data[0]->id)->toBe('obs-2')
        ->and($response->meta)->toBeInstanceOf(ObservationListMeta::class)
        ->and($response->meta->cursor)->toBe('eyJpZCI6Im9icy0yIn0=');
});

it('handles empty data and missing cursor', function () {
    $response = ObservationListResponse::fromArray([
        'data' => [],
        'meta' => [],
    ]);

    expect($response->data)->toBeEmpty()
        ->and($response->meta->cursor)->toBeNull();
});

it('handles missing keys gracefully', function () {
    $response = ObservationListResponse::fromArray([]);

    expect($response->data)->toBeEmpty()
        ->and($response->meta->cursor)->toBeNull();
});
