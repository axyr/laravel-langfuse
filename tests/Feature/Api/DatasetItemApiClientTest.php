<?php

declare(strict_types=1);

use Axyr\Langfuse\Api\DatasetItemApiClient;
use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Dto\CreateDatasetItemBody;
use Axyr\Langfuse\Dto\DatasetItemListResponse;
use Axyr\Langfuse\Dto\DatasetItemQuery;
use Axyr\Langfuse\Dto\DatasetItemResponse;
use Axyr\Langfuse\Enums\DatasetStatus;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\DatasetItemFixtures;

beforeEach(function () {
    $this->config = new LangfuseConfig(
        publicKey: 'pk-test',
        secretKey: 'sk-test',
        baseUrl: 'https://test.langfuse.com',
    );
});

it('fetches a single dataset item by id', function () {
    Http::fake([
        'test.langfuse.com/api/public/dataset-items/*' => Http::response(DatasetItemFixtures::datasetItem()),
    ]);

    $client = new DatasetItemApiClient($this->config);
    $result = $client->get('di-1');

    expect($result)->toBeInstanceOf(DatasetItemResponse::class)
        ->and($result->id)->toBe('di-1')
        ->and($result->status)->toBe(DatasetStatus::ACTIVE)
        ->and($result->datasetName)->toBe('qa-eval');

    Http::assertSent(function ($request) {
        return $request->method() === 'GET'
            && str_contains($request->url(), '/api/public/dataset-items/di-1')
            && $request->hasHeader('Authorization', 'Basic ' . base64_encode('pk-test:sk-test'));
    });
});

it('lists dataset items with filters', function () {
    Http::fake([
        'test.langfuse.com/api/public/dataset-items*' => Http::response(DatasetItemFixtures::datasetItemList()),
    ]);

    $client = new DatasetItemApiClient($this->config);
    $result = $client->list(new DatasetItemQuery(datasetName: 'qa-eval', page: 1, limit: 50));

    expect($result)->toBeInstanceOf(DatasetItemListResponse::class)
        ->and($result->data)->toHaveCount(2)
        ->and($result->data[0]->id)->toBe('di-1')
        ->and($result->meta->totalItems)->toBe(2);

    Http::assertSent(function ($request) {
        $url = $request->url();

        return str_contains($url, 'datasetName=qa-eval')
            && str_contains($url, 'page=1')
            && str_contains($url, 'limit=50');
    });
});

it('creates a dataset item via the api', function () {
    Http::fake([
        'test.langfuse.com/api/public/dataset-items' => Http::response(DatasetItemFixtures::datasetItem()),
    ]);

    $client = new DatasetItemApiClient($this->config);
    $result = $client->create(new CreateDatasetItemBody(
        datasetName: 'qa-eval',
        input: ['question' => 'What is 2+2?'],
    ));

    expect($result)->toBeInstanceOf(DatasetItemResponse::class)
        ->and($result->id)->toBe('di-1');

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), '/api/public/dataset-items')
            && $request['datasetName'] === 'qa-eval';
    });
});

it('deletes a dataset item', function () {
    Http::fake([
        'test.langfuse.com/api/public/dataset-items/*' => Http::response(['message' => 'deleted']),
    ]);

    $client = new DatasetItemApiClient($this->config);

    expect($client->delete('di-1'))->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->method() === 'DELETE'
            && str_contains($request->url(), '/api/public/dataset-items/di-1');
    });
});

it('returns false when delete fails', function () {
    Http::fake([
        'test.langfuse.com/api/public/dataset-items/*' => Http::response('Not Found', 404),
    ]);

    $client = new DatasetItemApiClient($this->config);

    expect($client->delete('missing'))->toBeFalse();
});

it('returns null on network error', function () {
    Http::fake([
        'test.langfuse.com/api/public/dataset-items/*' => fn() => throw new \Exception('Connection refused'),
    ]);

    $client = new DatasetItemApiClient($this->config);

    expect($client->get('di-1'))->toBeNull();
});
