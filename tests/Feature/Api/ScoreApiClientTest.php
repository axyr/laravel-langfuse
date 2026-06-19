<?php

declare(strict_types=1);

use Axyr\Langfuse\Api\ScoreApiClient;
use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Dto\ScoreListResponse;
use Axyr\Langfuse\Dto\ScoreQuery;
use Axyr\Langfuse\Dto\ScoreResponse;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\ScoreFixtures;

beforeEach(function () {
    $this->config = new LangfuseConfig(
        publicKey: 'pk-test',
        secretKey: 'sk-test',
        baseUrl: 'https://test.langfuse.com',
    );
});

it('deletes score successfully', function () {
    Http::fake([
        'test.langfuse.com/api/public/scores/*' => Http::response('', 204),
    ]);

    $client = new ScoreApiClient($this->config);
    $result = $client->delete('score-123');

    expect($result)->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->method() === 'DELETE'
            && str_contains($request->url(), '/api/public/scores/score-123')
            && $request->hasHeader('Authorization', 'Basic ' . base64_encode('pk-test:sk-test'));
    });
});

it('returns false on http error', function () {
    Http::fake([
        'test.langfuse.com/api/public/scores/*' => Http::response('Not Found', 404),
    ]);

    $client = new ScoreApiClient($this->config);
    $result = $client->delete('nonexistent');

    expect($result)->toBeFalse();
});

it('returns false on network error', function () {
    Http::fake([
        'test.langfuse.com/api/public/scores/*' => fn() => throw new \Exception('Connection refused'),
    ]);

    $client = new ScoreApiClient($this->config);
    $result = $client->delete('score-123');

    expect($result)->toBeFalse();
});

it('fetches a single score by id', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/scores/*' => Http::response(ScoreFixtures::numericScore()),
    ]);

    $client = new ScoreApiClient($this->config);
    $result = $client->get('score-abc');

    expect($result)->toBeInstanceOf(ScoreResponse::class)
        ->and($result->id)->toBe('score-abc')
        ->and($result->dataType)->toBe('NUMERIC')
        ->and($result->value)->toBe(0.95);

    Http::assertSent(function ($request) {
        return $request->method() === 'GET'
            && str_contains($request->url(), '/api/public/v2/scores/score-abc')
            && $request->hasHeader('Authorization', 'Basic ' . base64_encode('pk-test:sk-test'));
    });
});

it('returns null when a single score is not found', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/scores/*' => Http::response('Not Found', 404),
    ]);

    $client = new ScoreApiClient($this->config);

    expect($client->get('missing'))->toBeNull();
});

it('returns null when a single score fetch errors', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/scores/*' => fn() => throw new \Exception('Connection refused'),
    ]);

    $client = new ScoreApiClient($this->config);

    expect($client->get('score-abc'))->toBeNull();
});

it('fetches many scores', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/scores*' => Http::response(ScoreFixtures::scoreList()),
    ]);

    $client = new ScoreApiClient($this->config);
    $result = $client->getMany();

    expect($result)->toBeInstanceOf(ScoreListResponse::class)
        ->and($result->data)->toHaveCount(2)
        ->and($result->data[0]->id)->toBe('score-abc')
        ->and($result->data[1]->id)->toBe('score-cat')
        ->and($result->meta->totalItems)->toBe(2);
});

it('passes query filters when fetching many scores', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/scores*' => Http::response(ScoreFixtures::scoreList()),
    ]);

    $client = new ScoreApiClient($this->config);
    $client->getMany(new ScoreQuery(
        page: 2,
        limit: 25,
        name: 'accuracy',
        datasetRunId: 'run-789',
    ));

    Http::assertSent(function ($request) {
        $url = $request->url();

        return str_contains($url, 'page=2')
            && str_contains($url, 'limit=25')
            && str_contains($url, 'name=accuracy')
            && str_contains($url, 'datasetRunId=run-789');
    });
});

it('returns null when fetching many scores errors', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/scores*' => fn() => throw new \Exception('Connection refused'),
    ]);

    $client = new ScoreApiClient($this->config);

    expect($client->getMany())->toBeNull();
});

it('serialises array filters as repeated bare query keys', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/scores*' => Http::response(ScoreFixtures::scoreList()),
    ]);

    $client = new ScoreApiClient($this->config);
    $client->getMany(new ScoreQuery(environment: ['prod', 'staging'], traceTags: ['v2']));

    Http::assertSent(function ($request) {
        $url = $request->url();

        return str_contains($url, 'environment=prod')
            && str_contains($url, 'environment=staging')
            && str_contains($url, 'traceTags=v2')
            && ! str_contains($url, 'environment%5B0%5D')
            && ! str_contains($url, 'environment[0]');
    });
});
