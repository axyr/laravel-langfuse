<?php

declare(strict_types=1);

use Axyr\Langfuse\Api\ExperimentApiClient;
use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Dto\ExperimentItemListResponse;
use Axyr\Langfuse\Dto\ExperimentItemQuery;
use Axyr\Langfuse\Dto\ExperimentListResponse;
use Axyr\Langfuse\Dto\ExperimentQuery;
use Axyr\Langfuse\Dto\ExperimentResponse;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\ExperimentFixtures;

beforeEach(function () {
    $this->config = new LangfuseConfig(
        publicKey: 'pk-test',
        secretKey: 'sk-test',
        baseUrl: 'https://test.langfuse.com',
    );
    $this->client = new ExperimentApiClient($this->config);
});

it('lists experiments with the required start-time window', function () {
    Http::fake([
        'test.langfuse.com/api/public/experiments*' => Http::response(ExperimentFixtures::experimentList()),
    ]);

    $result = $this->client->listExperiments(new ExperimentQuery(
        fromStartTime: '2024-05-01T00:00:00Z',
        toStartTime: '2024-05-02T00:00:00Z',
        fields: 'core,metadata,scores',
        limit: 10,
    ));

    expect($result)->toBeInstanceOf(ExperimentListResponse::class)
        ->and($result->data)->toHaveCount(1)
        ->and($result->data[0]->id)->toBe('experiment-1')
        ->and($result->data[0]->name)->toBe('nightly-eval')
        ->and($result->data[0]->itemCount)->toBe(2)
        ->and($result->data[0]->datasetId)->toBe('dataset-1')
        ->and($result->data[0]->metadata)->toBe(['model' => 'gpt-4'])
        ->and($result->data[0]->scores)->toHaveCount(1)
        ->and($result->data[0]->scores[0]->id)->toBe('score-abc')
        ->and($result->meta->cursor)->toBe('eyJpZCI6ImV4cGVyaW1lbnQtMSJ9');

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return $request->method() === 'GET'
            && str_contains($url, '/api/public/experiments?')
            && str_contains($url, 'fromStartTime=2024-05-01T00:00:00Z')
            && str_contains($url, 'toStartTime=2024-05-02T00:00:00Z')
            && str_contains($url, 'fields=core,metadata,scores')
            && str_contains($url, 'limit=10')
            && $request->hasHeader('Authorization', 'Basic ' . base64_encode('pk-test:sk-test'));
    });
});

it('serialises experiment list filters as comma-separated values', function () {
    Http::fake([
        'test.langfuse.com/api/public/experiments*' => Http::response(ExperimentFixtures::experimentList()),
    ]);

    $this->client->listExperiments(new ExperimentQuery(
        fromStartTime: '2024-05-01T00:00:00Z',
        id: ['a', 'b'],
        datasetId: ['dataset-1'],
    ));

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'id=a,b')
            && str_contains($url, 'datasetId=dataset-1')
            && ! str_contains($url, 'id[0]');
    });
});

it('gets one experiment by filtering the list', function () {
    Http::fake([
        'test.langfuse.com/api/public/experiments*' => Http::response(ExperimentFixtures::experimentList()),
    ]);

    $result = $this->client->getExperiment('experiment-1', '2024-05-01T00:00:00Z');

    expect($result)->toBeInstanceOf(ExperimentResponse::class)
        ->and($result->id)->toBe('experiment-1');

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'id=experiment-1') && str_contains($url, 'limit=1');
    });
});

it('returns null when the experiment filter matches nothing', function () {
    Http::fake([
        'test.langfuse.com/api/public/experiments*' => Http::response(['data' => [], 'meta' => []]),
    ]);

    expect($this->client->getExperiment('missing', '2024-05-01T00:00:00Z'))->toBeNull();
});

it('lists experiment items', function () {
    Http::fake([
        'test.langfuse.com/api/public/experiment-items*' => Http::response(ExperimentFixtures::experimentItemList()),
    ]);

    $result = $this->client->listExperimentItems(new ExperimentItemQuery(
        fromStartTime: '2024-05-01T00:00:00Z',
        experimentId: ['experiment-1'],
        fields: 'core,dataset,io,scores',
    ));

    expect($result)->toBeInstanceOf(ExperimentItemListResponse::class)
        ->and($result->data)->toHaveCount(1)
        ->and($result->data[0]->id)->toBe('aaaaaaaaaaaaaaaa')
        ->and($result->data[0]->traceId)->toBe('0192f1b42c7e7a1b8d3e9f0a1b2c3d4e')
        ->and($result->data[0]->experimentItemId)->toBe('item-1')
        ->and($result->data[0]->expectedOutput)->toBe('An LLM observability platform.')
        ->and($result->data[0]->scores)->toHaveCount(1)
        ->and($result->meta->cursor)->toBeNull()
        ->and($result->meta->hasMore())->toBeFalse();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, '/api/public/experiment-items?')
            && str_contains($url, 'fromStartTime=2024-05-01T00:00:00Z')
            && str_contains($url, 'experimentId=experiment-1')
            && str_contains($url, 'fields=core,dataset,io,scores');
    });
});

it('returns null when an experiment request fails', function () {
    Http::fake([
        'test.langfuse.com/api/public/experiments*' => Http::response('Not Found', 404),
        'test.langfuse.com/api/public/experiment-items*' => Http::response('Not Found', 404),
    ]);

    expect($this->client->listExperiments(new ExperimentQuery(fromStartTime: '2024-05-01T00:00:00Z')))->toBeNull()
        ->and($this->client->listExperimentItems(new ExperimentItemQuery(fromStartTime: '2024-05-01T00:00:00Z')))->toBeNull();
});

it('returns null on a network error', function () {
    Http::fake([
        'test.langfuse.com/api/public/experiments*' => fn() => throw new \Exception('Connection refused'),
        'test.langfuse.com/api/public/experiment-items*' => fn() => throw new \Exception('Connection refused'),
    ]);

    expect($this->client->listExperiments(new ExperimentQuery(fromStartTime: '2024-05-01T00:00:00Z')))->toBeNull()
        ->and($this->client->listExperimentItems(new ExperimentItemQuery(fromStartTime: '2024-05-01T00:00:00Z')))->toBeNull();
});
