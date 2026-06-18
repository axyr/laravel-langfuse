<?php

declare(strict_types=1);

use Axyr\Langfuse\Api\DatasetApiClient;
use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Dto\CreateDatasetBody;
use Axyr\Langfuse\Dto\DatasetListResponse;
use Axyr\Langfuse\Dto\DatasetResponse;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\DatasetFixtures;

beforeEach(function () {
    $this->config = new LangfuseConfig(
        publicKey: 'pk-test',
        secretKey: 'sk-test',
        baseUrl: 'https://test.langfuse.com',
    );
});

it('fetches a single dataset by name', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/datasets/*' => Http::response(DatasetFixtures::dataset()),
    ]);

    $client = new DatasetApiClient($this->config);
    $result = $client->get('qa-eval');

    expect($result)->toBeInstanceOf(DatasetResponse::class)
        ->and($result->name)->toBe('qa-eval')
        ->and($result->projectId)->toBe('proj-1');

    Http::assertSent(function ($request) {
        return $request->method() === 'GET'
            && str_contains($request->url(), '/api/public/v2/datasets/qa-eval')
            && $request->hasHeader('Authorization', 'Basic ' . base64_encode('pk-test:sk-test'));
    });
});

it('returns null when a dataset is not found', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/datasets/*' => Http::response('Not Found', 404),
    ]);

    $client = new DatasetApiClient($this->config);

    expect($client->get('missing'))->toBeNull();
});

it('lists datasets with pagination', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/datasets*' => Http::response(DatasetFixtures::datasetList()),
    ]);

    $client = new DatasetApiClient($this->config);
    $result = $client->list(page: 1, limit: 50);

    expect($result)->toBeInstanceOf(DatasetListResponse::class)
        ->and($result->data)->toHaveCount(2)
        ->and($result->data[0]->name)->toBe('qa-eval')
        ->and($result->meta->totalItems)->toBe(2);

    Http::assertSent(function ($request) {
        $url = $request->url();

        return str_contains($url, 'page=1') && str_contains($url, 'limit=50');
    });
});

it('creates a dataset via the api', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/datasets' => Http::response(DatasetFixtures::dataset()),
    ]);

    $client = new DatasetApiClient($this->config);
    $result = $client->create(new CreateDatasetBody(
        name: 'qa-eval',
        description: 'QA evaluation set',
    ));

    expect($result)->toBeInstanceOf(DatasetResponse::class)
        ->and($result->name)->toBe('qa-eval');

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), '/api/public/v2/datasets')
            && $request['name'] === 'qa-eval'
            && $request['description'] === 'QA evaluation set';
    });
});

it('returns null on dataset create failure', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/datasets' => Http::response('Server Error', 500),
    ]);

    $client = new DatasetApiClient($this->config);

    expect($client->create(new CreateDatasetBody(name: 'qa-eval')))->toBeNull();
});

it('returns null on network error', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/datasets/*' => fn() => throw new \Exception('Connection refused'),
    ]);

    $client = new DatasetApiClient($this->config);

    expect($client->get('qa-eval'))->toBeNull();
});
