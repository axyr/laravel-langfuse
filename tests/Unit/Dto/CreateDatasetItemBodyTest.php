<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\CreateDatasetItemBody;
use Axyr\Langfuse\Enums\DatasetStatus;

it('serialises only the dataset name when no optionals are set', function () {
    $body = new CreateDatasetItemBody(datasetName: 'qa-eval');

    expect($body->toArray())->toBe(['datasetName' => 'qa-eval']);
});

it('serialises provided optionals and the status enum value', function () {
    $body = new CreateDatasetItemBody(
        datasetName: 'qa-eval',
        input: ['question' => 'What is 2+2?'],
        expectedOutput: ['answer' => '4'],
        id: 'di-1',
        status: DatasetStatus::ARCHIVED,
    );

    expect($body->toArray())->toBe([
        'datasetName' => 'qa-eval',
        'input' => ['question' => 'What is 2+2?'],
        'expectedOutput' => ['answer' => '4'],
        'id' => 'di-1',
        'status' => 'ARCHIVED',
    ]);
});
