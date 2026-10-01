<?php

declare(strict_types=1);

use Axyr\Langfuse\Api\ScoreApiClient;
use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\IngestionApiClientInterface;
use Axyr\Langfuse\Dto\IngestionBatch;
use Axyr\Langfuse\Dto\IngestionEvent;
use Axyr\Langfuse\Dto\IngestionResponse;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Dto\ScoreListResponse;
use Axyr\Langfuse\Dto\ScoreQuery;
use Axyr\Langfuse\Dto\ScoreResponse;
use Axyr\Langfuse\Enums\EventType;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\ScoreFixtures;

beforeEach(function () {
    $this->config = new LangfuseConfig(
        publicKey: 'pk-test',
        secretKey: 'sk-test',
        baseUrl: 'https://test.langfuse.com',
    );
    $this->ingestion = Mockery::mock(IngestionApiClientInterface::class);
    $this->client = new ScoreApiClient($this->config, $this->ingestion);
});

it('writes a score batch through the ingestion endpoint', function () {
    $batch = new IngestionBatch(batch: [
        new IngestionEvent(
            id: 'evt-1',
            type: EventType::ScoreCreate,
            timestamp: '2024-01-01T00:00:00Z',
            body: new ScoreBody(name: 'accuracy', id: 'score-1', value: 1.0),
        ),
    ]);

    $response = IngestionResponse::fromArray(['successes' => [['id' => 'evt-1', 'status' => 201]], 'errors' => []]);

    $this->ingestion->shouldReceive('send')->once()->with($batch)->andReturn($response);

    expect($this->client->ingest($batch))->toBe($response);
});

it('deletes score successfully', function () {
    Http::fake([
        'test.langfuse.com/api/public/scores/*' => Http::response('', 204),
    ]);

    expect($this->client->delete('score-123'))->toBeTrue();

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

    expect($this->client->delete('nonexistent'))->toBeFalse();
});

it('returns false on network error', function () {
    Http::fake([
        'test.langfuse.com/api/public/scores/*' => fn() => throw new \Exception('Connection refused'),
    ]);

    expect($this->client->delete('score-123'))->toBeFalse();
});

it('fetches a single score with an id filter on v3', function () {
    Http::fake([
        'test.langfuse.com/api/public/v3/scores*' => Http::response([
            'data' => [ScoreFixtures::numericScore()],
            'meta' => ['limit' => 1],
        ]),
    ]);

    $result = $this->client->get('score-abc');

    expect($result)->toBeInstanceOf(ScoreResponse::class)
        ->and($result->id)->toBe('score-abc')
        ->and($result->dataType)->toBe('NUMERIC')
        ->and($result->value)->toBe(0.95);

    Http::assertSent(function ($request) {
        $url = $request->url();

        return $request->method() === 'GET'
            && str_contains($url, '/api/public/v3/scores?')
            && str_contains($url, 'id=score-abc')
            && str_contains($url, 'limit=1')
            && str_contains(urldecode($url), 'fields=details,subject,annotation')
            && $request->hasHeader('Authorization', 'Basic ' . base64_encode('pk-test:sk-test'));
    });
});

it('returns null when the id filter matches nothing', function () {
    Http::fake([
        'test.langfuse.com/api/public/v3/scores*' => Http::response(['data' => [], 'meta' => ['limit' => 1]]),
    ]);

    expect($this->client->get('missing'))->toBeNull();
});

it('returns null when a single score is not found', function () {
    Http::fake([
        'test.langfuse.com/api/public/v3/scores*' => Http::response('Not Found', 404),
    ]);

    expect($this->client->get('missing'))->toBeNull();
});

it('returns null when a single score fetch errors', function () {
    Http::fake([
        'test.langfuse.com/api/public/v3/scores*' => fn() => throw new \Exception('Connection refused'),
    ]);

    expect($this->client->get('score-abc'))->toBeNull();
});

it('fetches many scores with cursor meta', function () {
    Http::fake([
        'test.langfuse.com/api/public/v3/scores*' => Http::response(ScoreFixtures::scoreList()),
    ]);

    $result = $this->client->getMany();

    expect($result)->toBeInstanceOf(ScoreListResponse::class)
        ->and($result->data)->toHaveCount(2)
        ->and($result->data[0]->id)->toBe('score-abc')
        ->and($result->data[1]->id)->toBe('score-cat')
        ->and($result->meta->limit)->toBe(50)
        ->and($result->meta->cursor)->toBe('eyJpZCI6InNjb3JlLWNhdCJ9')
        ->and($result->meta->hasMore())->toBeTrue();
});

it('passes query filters when fetching many scores', function () {
    Http::fake([
        'test.langfuse.com/api/public/v3/scores*' => Http::response(ScoreFixtures::scoreList()),
    ]);

    $this->client->getMany(new ScoreQuery(
        limit: 25,
        cursor: 'abc',
        name: ['accuracy'],
        experimentId: ['run-789'],
        fromTimestamp: '2024-01-01T00:00:00Z',
    ));

    Http::assertSent(function ($request) {
        $url = $request->url();

        return str_contains($url, 'limit=25')
            && str_contains($url, 'cursor=abc')
            && str_contains($url, 'name=accuracy')
            && str_contains($url, 'experimentId=run-789')
            && str_contains(urldecode($url), 'fromTimestamp=2024-01-01T00:00:00Z');
    });
});

it('serialises list filters as one comma-separated value', function () {
    Http::fake([
        'test.langfuse.com/api/public/v3/scores*' => Http::response(ScoreFixtures::scoreList()),
    ]);

    $this->client->getMany(new ScoreQuery(environment: ['prod', 'staging'], id: ['a', 'b']));

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'environment=prod,staging')
            && str_contains($url, 'id=a,b')
            && ! str_contains($url, 'environment[0]');
    });
});

it('returns null when fetching many scores errors', function () {
    Http::fake([
        'test.langfuse.com/api/public/v3/scores*' => fn() => throw new \Exception('Connection refused'),
    ]);

    expect($this->client->getMany())->toBeNull();
});

it('returns null when the score list request fails', function () {
    Http::fake([
        'test.langfuse.com/api/public/v3/scores*' => Http::response('Server Error', 500),
    ]);

    expect($this->client->getMany())->toBeNull();
});
