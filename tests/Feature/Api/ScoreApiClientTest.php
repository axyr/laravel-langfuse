<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Langfuse\Api\ScoreApiClient;
use Langfuse\Config\LangfuseConfig;

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
        'test.langfuse.com/api/public/scores/*' => fn () => throw new \Exception('Connection refused'),
    ]);

    $client = new ScoreApiClient($this->config);
    $result = $client->delete('score-123');

    expect($result)->toBeFalse();
});
