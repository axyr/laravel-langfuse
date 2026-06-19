<?php

declare(strict_types=1);

use Axyr\Langfuse\Api\DatasetRunApiClient;
use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Dto\CreateDatasetRunItemBody;
use Axyr\Langfuse\Dto\DatasetRunItemListResponse;
use Axyr\Langfuse\Dto\DatasetRunItemResponse;
use Axyr\Langfuse\Dto\DatasetRunListResponse;
use Axyr\Langfuse\Dto\DatasetRunWithItemsResponse;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\DatasetRunFixtures;

beforeEach(function () {
    $this->config = new LangfuseConfig(
        publicKey: 'pk-test',
        secretKey: 'sk-test',
        baseUrl: 'https://test.langfuse.com',
    );
});

it('fetches a dataset run with its items', function () {
    Http::fake([
        'test.langfuse.com/api/public/datasets/*/runs/*' => Http::response(DatasetRunFixtures::datasetRunWithItems()),
    ]);

    $client = new DatasetRunApiClient($this->config);
    $result = $client->getRun('qa-eval', 'run-2024-05');

    expect($result)->toBeInstanceOf(DatasetRunWithItemsResponse::class)
        ->and($result->run->name)->toBe('run-2024-05')
        ->and($result->datasetRunItems)->toHaveCount(1)
        ->and($result->datasetRunItems[0]->traceId)->toBe('trace-abc');

    Http::assertSent(function ($request) {
        return $request->method() === 'GET'
            && str_contains($request->url(), '/api/public/datasets/qa-eval/runs/run-2024-05')
            && $request->hasHeader('Authorization', 'Basic ' . base64_encode('pk-test:sk-test'));
    });
});

it('lists dataset runs', function () {
    Http::fake([
        'test.langfuse.com/api/public/datasets/*/runs*' => Http::response(DatasetRunFixtures::datasetRunList()),
    ]);

    $client = new DatasetRunApiClient($this->config);
    $result = $client->listRuns('qa-eval', page: 1, limit: 50);

    expect($result)->toBeInstanceOf(DatasetRunListResponse::class)
        ->and($result->data)->toHaveCount(1)
        ->and($result->data[0]->name)->toBe('run-2024-05')
        ->and($result->meta->totalItems)->toBe(1);

    Http::assertSent(function ($request) {
        $url = $request->url();

        return str_contains($url, '/api/public/datasets/qa-eval/runs')
            && str_contains($url, 'page=1')
            && str_contains($url, 'limit=50');
    });
});

it('deletes a dataset run', function () {
    Http::fake([
        'test.langfuse.com/api/public/datasets/*/runs/*' => Http::response(['message' => 'deleted']),
    ]);

    $client = new DatasetRunApiClient($this->config);

    expect($client->deleteRun('qa-eval', 'run-2024-05'))->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->method() === 'DELETE'
            && str_contains($request->url(), '/api/public/datasets/qa-eval/runs/run-2024-05');
    });
});

it('creates a dataset run item', function () {
    Http::fake([
        'test.langfuse.com/api/public/dataset-run-items' => Http::response(DatasetRunFixtures::datasetRunItem()),
    ]);

    $client = new DatasetRunApiClient($this->config);
    $result = $client->createRunItem(new CreateDatasetRunItemBody(
        runName: 'run-2024-05',
        datasetItemId: 'di-1',
        traceId: 'trace-abc',
    ));

    expect($result)->toBeInstanceOf(DatasetRunItemResponse::class)
        ->and($result->id)->toBe('dri-1');

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), '/api/public/dataset-run-items')
            && $request['runName'] === 'run-2024-05'
            && $request['datasetItemId'] === 'di-1';
    });
});

it('lists dataset run items', function () {
    Http::fake([
        'test.langfuse.com/api/public/dataset-run-items*' => Http::response(DatasetRunFixtures::datasetRunItemList()),
    ]);

    $client = new DatasetRunApiClient($this->config);
    $result = $client->listRunItems('ds-1', 'run-2024-05', page: 1, limit: 50);

    expect($result)->toBeInstanceOf(DatasetRunItemListResponse::class)
        ->and($result->data)->toHaveCount(1)
        ->and($result->data[0]->id)->toBe('dri-1');

    Http::assertSent(function ($request) {
        $url = $request->url();

        return str_contains($url, 'datasetId=ds-1')
            && str_contains($url, 'runName=run-2024-05');
    });
});

it('returns null when fetching a run errors', function () {
    Http::fake([
        'test.langfuse.com/api/public/datasets/*/runs/*' => fn() => throw new \Exception('Connection refused'),
    ]);

    $client = new DatasetRunApiClient($this->config);

    expect($client->getRun('qa-eval', 'run-2024-05'))->toBeNull();
});

it('returns false when deleting a run fails', function () {
    Http::fake([
        'test.langfuse.com/api/public/datasets/*/runs/*' => Http::response('Not Found', 404),
    ]);

    $client = new DatasetRunApiClient($this->config);

    expect($client->deleteRun('qa-eval', 'missing'))->toBeFalse();
});
