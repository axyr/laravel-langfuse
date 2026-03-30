<?php

declare(strict_types=1);

use Langfuse\Dto\IngestionError;
use Langfuse\Dto\IngestionResponse;
use Langfuse\Dto\IngestionSuccess;

it('can be created from array with successes', function () {
    $response = IngestionResponse::fromArray([
        'successes' => [
            ['id' => 'evt-1', 'status' => 201],
            ['id' => 'evt-2', 'status' => 201],
        ],
        'errors' => [],
    ]);

    expect($response->successes)->toHaveCount(2)
        ->and($response->errors)->toHaveCount(0)
        ->and($response->successes[0])->toBeInstanceOf(IngestionSuccess::class)
        ->and($response->successes[0]->id)->toBe('evt-1')
        ->and($response->successes[0]->status)->toBe(201);
});

it('can be created from array with errors', function () {
    $response = IngestionResponse::fromArray([
        'successes' => [],
        'errors' => [
            ['id' => 'evt-1', 'status' => 400, 'message' => 'Invalid body', 'error' => 'ValidationError'],
        ],
    ]);

    expect($response->errors)->toHaveCount(1)
        ->and($response->errors[0])->toBeInstanceOf(IngestionError::class)
        ->and($response->errors[0]->id)->toBe('evt-1')
        ->and($response->errors[0]->status)->toBe(400)
        ->and($response->errors[0]->message)->toBe('Invalid body')
        ->and($response->errors[0]->error)->toBe('ValidationError');
});

it('handles missing keys gracefully', function () {
    $response = IngestionResponse::fromArray([]);

    expect($response->successes)->toBe([])
        ->and($response->errors)->toBe([]);
});

it('reports hasErrors correctly', function () {
    $withErrors = IngestionResponse::fromArray([
        'successes' => [],
        'errors' => [['id' => 'evt-1', 'status' => 400, 'message' => 'Bad']],
    ]);

    $withoutErrors = IngestionResponse::fromArray([
        'successes' => [['id' => 'evt-1', 'status' => 201]],
        'errors' => [],
    ]);

    expect($withErrors->hasErrors())->toBeTrue()
        ->and($withoutErrors->hasErrors())->toBeFalse();
});

it('handles error without optional error field', function () {
    $response = IngestionResponse::fromArray([
        'successes' => [],
        'errors' => [['id' => 'evt-1', 'status' => 500, 'message' => 'Internal']],
    ]);

    expect($response->errors[0]->error)->toBeNull();
});
