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
    $this->client = new ObservationApiClient($this->config);
});

it('fetches a single observation with an id filter on v2', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/observations*' => Http::response([
            'data' => [ObservationFixtures::generation()],
            'meta' => [],
        ]),
    ]);

    $result = $this->client->get('0192f1b42c7e7a1b');

    expect($result)->toBeInstanceOf(ObservationResponse::class)
        ->and($result->id)->toBe('0192f1b42c7e7a1b')
        ->and($result->type)->toBe('GENERATION')
        ->and($result->model)->toBe('gpt-4')
        ->and($result->latency)->toBe(2.0);

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return $request->method() === 'GET'
            && str_contains($url, '/api/public/v2/observations?')
            && str_contains($url, '"column":"id"')
            && str_contains($url, '"value":"0192f1b42c7e7a1b"')
            && str_contains($url, 'limit=1')
            && $request->hasHeader('Authorization', 'Basic ' . base64_encode('pk-test:sk-test'));
    });
});

it('defaults the get-by-id window to the last 30 days', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/observations*' => Http::response(['data' => [], 'meta' => []]),
    ]);

    $this->client->get('0192f1b42c7e7a1b');

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        preg_match('/fromStartTime=([^&]+)/', $url, $matches);

        return isset($matches[1])
            && strtotime($matches[1]) < time()
            && strtotime($matches[1]) > time() - 31 * 86400;
    });
});

it('passes an explicit window and field selection to the get-by-id lookup', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/observations*' => Http::response(['data' => [], 'meta' => []]),
    ]);

    $this->client->get(
        '0192f1b42c7e7a1b',
        fromStartTime: '2024-05-01T00:00:00Z',
        toStartTime: '2024-05-02T00:00:00Z',
        fields: 'core,basic,io',
    );

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'fromStartTime=2024-05-01T00:00:00Z')
            && str_contains($url, 'toStartTime=2024-05-02T00:00:00Z')
            && str_contains($url, 'fields=core,basic,io');
    });
});

it('returns null when the id filter matches nothing', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/observations*' => Http::response(['data' => [], 'meta' => []]),
    ]);

    expect($this->client->get('missing'))->toBeNull();
});

it('returns null when a single observation is not found', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/observations*' => Http::response('Not Found', 404),
    ]);

    expect($this->client->get('missing'))->toBeNull();
});

it('returns null when a single observation fetch errors', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/observations*' => fn() => throw new \Exception('Connection refused'),
    ]);

    expect($this->client->get('0192f1b42c7e7a1b'))->toBeNull();
});

it('fetches many observations with cursor pagination', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/observations*' => Http::response(ObservationFixtures::v2List()),
    ]);

    $result = $this->client->getMany();

    expect($result)->toBeInstanceOf(ObservationListResponse::class)
        ->and($result->data)->toHaveCount(1)
        ->and($result->data[0]->id)->toBe('bbbbbbbbbbbbbbbb')
        ->and($result->data[0]->model)->toBe('text-embedding-3')
        ->and($result->meta->cursor)->toBe('eyJpZCI6Im9icy0yIn0=');
});

it('passes query filters when fetching many observations', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/observations*' => Http::response(ObservationFixtures::v2List()),
    ]);

    $this->client->getMany(new ObservationQuery(
        fields: 'core,basic,usage',
        limit: 100,
        type: 'GENERATION',
        traceId: '0192f1b42c7e7a1b8d3e9f0a1b2c3d4e',
        sessionId: 'session-1',
        isRootObservation: true,
    ));

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'fields=core,basic,usage')
            && str_contains($url, 'limit=100')
            && str_contains($url, 'type=GENERATION')
            && str_contains($url, 'traceId=0192f1b42c7e7a1b8d3e9f0a1b2c3d4e')
            && str_contains($url, 'sessionId=session-1')
            && str_contains($url, 'isRootObservation=true');
    });
});

it('returns null when fetching many observations errors', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/observations*' => fn() => throw new \Exception('Connection refused'),
    ]);

    expect($this->client->getMany())->toBeNull();
});

it('serialises array filters as repeated bare query keys', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/observations*' => Http::response(ObservationFixtures::v2List()),
    ]);

    $this->client->getMany(new ObservationQuery(environment: ['prod', 'staging']));

    Http::assertSent(function ($request) {
        $url = $request->url();

        return str_contains($url, 'environment=prod')
            && str_contains($url, 'environment=staging')
            && ! str_contains($url, 'environment%5B0%5D')
            && ! str_contains($url, 'environment[0]');
    });
});
