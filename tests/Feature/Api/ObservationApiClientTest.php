<?php

declare(strict_types=1);

use Axyr\Langfuse\Api\ObservationApiClient;
use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Dto\ObservationListResponse;
use Axyr\Langfuse\Dto\ObservationQuery;
use Axyr\Langfuse\Dto\ObservationResponse;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\ObservationFixtures;

beforeEach(function () {
    $this->config = new LangfuseConfig(
        publicKey: 'pk-test',
        secretKey: 'sk-test',
        baseUrl: 'https://test.langfuse.com',
    );
});

it('fetches a single observation by id', function () {
    Http::fake([
        'test.langfuse.com/api/public/observations/*' => Http::response(ObservationFixtures::generation()),
    ]);

    $client = new ObservationApiClient($this->config);
    $result = $client->get('obs-1');

    expect($result)->toBeInstanceOf(ObservationResponse::class)
        ->and($result->id)->toBe('obs-1')
        ->and($result->type)->toBe('GENERATION')
        ->and($result->model)->toBe('gpt-4')
        ->and($result->latency)->toBe(2.0);

    Http::assertSent(function ($request) {
        return $request->method() === 'GET'
            && str_contains($request->url(), '/api/public/observations/obs-1')
            && $request->hasHeader('Authorization', 'Basic ' . base64_encode('pk-test:sk-test'));
    });
});

it('returns null when a single observation is not found', function () {
    Http::fake([
        'test.langfuse.com/api/public/observations/*' => Http::response('Not Found', 404),
    ]);

    $client = new ObservationApiClient($this->config);

    expect($client->get('missing'))->toBeNull();
});

it('returns null when a single observation fetch errors', function () {
    Http::fake([
        'test.langfuse.com/api/public/observations/*' => fn() => throw new \Exception('Connection refused'),
    ]);

    $client = new ObservationApiClient($this->config);

    expect($client->get('obs-1'))->toBeNull();
});

it('fetches many observations with cursor pagination', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/observations*' => Http::response(ObservationFixtures::v2List()),
    ]);

    $client = new ObservationApiClient($this->config);
    $result = $client->getMany();

    expect($result)->toBeInstanceOf(ObservationListResponse::class)
        ->and($result->data)->toHaveCount(1)
        ->and($result->data[0]->id)->toBe('obs-2')
        ->and($result->data[0]->model)->toBe('text-embedding-3')
        ->and($result->meta->cursor)->toBe('eyJpZCI6Im9icy0yIn0=');
});

it('passes query filters when fetching many observations', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/observations*' => Http::response(ObservationFixtures::v2List()),
    ]);

    $client = new ObservationApiClient($this->config);
    $client->getMany(new ObservationQuery(
        fields: 'core,basic,usage',
        limit: 100,
        type: 'GENERATION',
        traceId: 'trace-123',
    ));

    Http::assertSent(function ($request) {
        $url = $request->url();

        return str_contains($url, 'fields=')
            && str_contains($url, 'limit=100')
            && str_contains($url, 'type=GENERATION')
            && str_contains($url, 'traceId=trace-123');
    });
});

it('returns null when fetching many observations errors', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/observations*' => fn() => throw new \Exception('Connection refused'),
    ]);

    $client = new ObservationApiClient($this->config);

    expect($client->getMany())->toBeNull();
});

it('serialises array filters as repeated bare query keys', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/observations*' => Http::response(ObservationFixtures::v2List()),
    ]);

    $client = new ObservationApiClient($this->config);
    $client->getMany(new ObservationQuery(environment: ['prod', 'staging']));

    Http::assertSent(function ($request) {
        $url = $request->url();

        return str_contains($url, 'environment=prod')
            && str_contains($url, 'environment=staging')
            && ! str_contains($url, 'environment%5B0%5D')
            && ! str_contains($url, 'environment[0]');
    });
});
