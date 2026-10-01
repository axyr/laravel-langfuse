<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\CursorMeta;
use Axyr\Langfuse\Dto\ExperimentContext;
use Axyr\Langfuse\Dto\ExperimentItemContext;
use Axyr\Langfuse\Dto\ExperimentItemListResponse;
use Axyr\Langfuse\Dto\ExperimentItemResponse;
use Axyr\Langfuse\Dto\ExperimentListResponse;
use Axyr\Langfuse\Dto\ExperimentResponse;
use Axyr\Langfuse\Dto\ScoreResponse;
use Tests\Fixtures\ExperimentFixtures;

it('creates an experiment from array', function () {
    $experiment = ExperimentResponse::fromArray(ExperimentFixtures::experiment());

    expect($experiment->id)->toBe('experiment-1')
        ->and($experiment->name)->toBe('nightly-eval')
        ->and($experiment->description)->toBe('nightly regression run')
        ->and($experiment->startTime)->toBe('2024-05-01T00:00:00.000Z')
        ->and($experiment->endTime)->toBe('2024-05-01T00:10:00.000Z')
        ->and($experiment->itemCount)->toBe(2)
        ->and($experiment->datasetId)->toBe('dataset-1')
        ->and($experiment->metadata)->toBe(['model' => 'gpt-4'])
        ->and($experiment->scores)->toHaveCount(1)
        ->and($experiment->scores[0])->toBeInstanceOf(ScoreResponse::class);
});

it('handles an experiment without the optional field groups', function () {
    $experiment = ExperimentResponse::fromArray(['id' => 'e', 'name' => 'n']);

    expect($experiment->metadata)->toBeNull()
        ->and($experiment->scores)->toBeNull()
        ->and($experiment->itemCount)->toBeNull();
});

it('handles missing experiment keys gracefully', function () {
    $experiment = ExperimentResponse::fromArray([]);

    expect($experiment->id)->toBe('')
        ->and($experiment->name)->toBe('')
        ->and($experiment->description)->toBeNull();
});

it('creates an experiment item from array', function () {
    $item = ExperimentItemResponse::fromArray(ExperimentFixtures::experimentItem());

    expect($item->id)->toBe('aaaaaaaaaaaaaaaa')
        ->and($item->traceId)->toBe('0192f1b42c7e7a1b8d3e9f0a1b2c3d4e')
        ->and($item->experimentId)->toBe('experiment-1')
        ->and($item->experimentName)->toBe('nightly-eval')
        ->and($item->experimentItemId)->toBe('item-1')
        ->and($item->experimentDatasetId)->toBe('dataset-1')
        ->and($item->experimentItemVersion)->toBe('2024-04-01T00:00:00.000Z')
        ->and($item->input)->toBe('What is Langfuse?')
        ->and($item->output)->toBe('An LLM observability platform.')
        ->and($item->expectedOutput)->toBe('An LLM observability platform.')
        ->and($item->metadata)->toBe(['run' => 1])
        ->and($item->experimentItemMetadata)->toBe(['difficulty' => 'easy'])
        ->and($item->experimentMetadata)->toBe(['model' => 'gpt-4'])
        ->and($item->experimentDescription)->toBe('nightly regression run')
        ->and($item->level)->toBe('DEFAULT')
        ->and($item->environment)->toBe('experiment')
        ->and($item->scores)->toHaveCount(1);
});

it('handles missing experiment item keys gracefully', function () {
    $item = ExperimentItemResponse::fromArray([]);

    expect($item->id)->toBe('')
        ->and($item->traceId)->toBe('')
        ->and($item->scores)->toBeNull();
});

it('creates an experiment list with cursor meta', function () {
    $response = ExperimentListResponse::fromArray(ExperimentFixtures::experimentList());

    expect($response->data)->toHaveCount(1)
        ->and($response->data[0])->toBeInstanceOf(ExperimentResponse::class)
        ->and($response->meta)->toBeInstanceOf(CursorMeta::class)
        ->and($response->meta->cursor)->toBe('eyJpZCI6ImV4cGVyaW1lbnQtMSJ9')
        ->and($response->meta->hasMore())->toBeTrue();
});

it('creates an experiment item list with cursor meta', function () {
    $response = ExperimentItemListResponse::fromArray(ExperimentFixtures::experimentItemList());

    expect($response->data)->toHaveCount(1)
        ->and($response->data[0])->toBeInstanceOf(ExperimentItemResponse::class)
        ->and($response->meta->cursor)->toBeNull()
        ->and($response->meta->hasMore())->toBeFalse();
});

it('handles missing list keys gracefully', function () {
    expect(ExperimentListResponse::fromArray([])->data)->toBeEmpty()
        ->and(ExperimentItemListResponse::fromArray([])->data)->toBeEmpty();
});

it('derives the experiment id from the name', function () {
    $experiment = ExperimentContext::named('nightly-eval', datasetId: 'ds-1', description: 'd', metadata: ['k' => 'v']);

    expect($experiment->id)->toBe('nightly-eval')
        ->and($experiment->name)->toBe('nightly-eval')
        ->and($experiment->datasetId)->toBe('ds-1')
        ->and($experiment->description)->toBe('d')
        ->and($experiment->metadata)->toBe(['k' => 'v']);
});

it('keeps an explicit experiment id apart from the name', function () {
    $experiment = new ExperimentContext(id: 'exp-1', name: 'nightly-eval');

    expect($experiment->id)->toBe('exp-1')
        ->and($experiment->name)->toBe('nightly-eval');
});

it('carries the dataset item on the item context', function () {
    $item = new ExperimentItemContext(
        itemId: 'item-1',
        expectedOutput: ['answer' => '4'],
        version: '2024-04-01T00:00:00.000Z',
        metadata: ['difficulty' => 'hard'],
    );

    expect($item->itemId)->toBe('item-1')
        ->and($item->expectedOutput)->toBe(['answer' => '4'])
        ->and($item->version)->toBe('2024-04-01T00:00:00.000Z')
        ->and($item->metadata)->toBe(['difficulty' => 'hard']);
});
