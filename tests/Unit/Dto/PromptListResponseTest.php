<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\PromptListItem;
use Axyr\Langfuse\Dto\PromptListMeta;
use Axyr\Langfuse\Dto\PromptListResponse;

it('can be created from array', function () {
    $response = PromptListResponse::fromArray([
        'data' => [
            ['name' => 'prompt-1', 'version' => 1, 'type' => 'text', 'labels' => ['production']],
            ['name' => 'prompt-2', 'version' => 3, 'type' => 'chat', 'labels' => []],
        ],
        'meta' => [
            'totalItems' => 2,
            'totalPages' => 1,
            'page' => 1,
            'limit' => 10,
        ],
    ]);

    expect($response->data)->toHaveCount(2)
        ->and($response->data[0])->toBeInstanceOf(PromptListItem::class)
        ->and($response->data[0]->name)->toBe('prompt-1')
        ->and($response->data[0]->version)->toBe(1)
        ->and($response->data[0]->type)->toBe('text')
        ->and($response->data[0]->labels)->toBe(['production'])
        ->and($response->data[1]->name)->toBe('prompt-2')
        ->and($response->data[1]->type)->toBe('chat')
        ->and($response->meta)->toBeInstanceOf(PromptListMeta::class)
        ->and($response->meta->totalItems)->toBe(2)
        ->and($response->meta->totalPages)->toBe(1)
        ->and($response->meta->page)->toBe(1)
        ->and($response->meta->limit)->toBe(10);
});

it('handles empty data', function () {
    $response = PromptListResponse::fromArray([
        'data' => [],
        'meta' => ['totalItems' => 0, 'totalPages' => 0, 'page' => 1, 'limit' => 10],
    ]);

    expect($response->data)->toBeEmpty()
        ->and($response->meta->totalItems)->toBe(0);
});

it('handles missing keys gracefully', function () {
    $response = PromptListResponse::fromArray([]);

    expect($response->data)->toBeEmpty()
        ->and($response->meta->totalItems)->toBe(0)
        ->and($response->meta->page)->toBe(1);
});

it('creates PromptListItem from array', function () {
    $item = PromptListItem::fromArray([
        'name' => 'test',
        'version' => 5,
        'type' => 'chat',
        'labels' => ['staging', 'v2'],
    ]);

    expect($item->name)->toBe('test')
        ->and($item->version)->toBe(5)
        ->and($item->type)->toBe('chat')
        ->and($item->labels)->toBe(['staging', 'v2']);
});

it('creates PromptListItem with defaults for missing fields', function () {
    $item = PromptListItem::fromArray([]);

    expect($item->name)->toBe('')
        ->and($item->version)->toBe(1)
        ->and($item->type)->toBe('text')
        ->and($item->labels)->toBe([]);
});

it('creates PromptListMeta from array', function () {
    $meta = PromptListMeta::fromArray([
        'totalItems' => 42,
        'totalPages' => 5,
        'page' => 3,
        'limit' => 10,
    ]);

    expect($meta->totalItems)->toBe(42)
        ->and($meta->totalPages)->toBe(5)
        ->and($meta->page)->toBe(3)
        ->and($meta->limit)->toBe(10);
});

it('creates PromptListMeta with defaults for missing fields', function () {
    $meta = PromptListMeta::fromArray([]);

    expect($meta->totalItems)->toBe(0)
        ->and($meta->totalPages)->toBe(0)
        ->and($meta->page)->toBe(1)
        ->and($meta->limit)->toBe(10);
});
