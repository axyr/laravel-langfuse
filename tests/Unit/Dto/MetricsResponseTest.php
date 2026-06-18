<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\MetricsResponse;
use Tests\Fixtures\MetricFixtures;

it('creates a response from array', function () {
    $response = MetricsResponse::fromArray(MetricFixtures::tracesByName());

    expect($response->data)->toHaveCount(2)
        ->and($response->data[0])->toBe(['name' => 'chat', 'count_count' => 42])
        ->and($response->data[1]['name'])->toBe('summarize');
});

it('handles empty data', function () {
    $response = MetricsResponse::fromArray(['data' => []]);

    expect($response->data)->toBeEmpty();
});

it('handles missing keys gracefully', function () {
    $response = MetricsResponse::fromArray([]);

    expect($response->data)->toBeEmpty();
});
