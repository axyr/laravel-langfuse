<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\CreateDatasetRunItemBody;

it('serialises only the required fields when no optionals are set', function () {
    $body = new CreateDatasetRunItemBody(runName: 'run-2024-05', datasetItemId: 'di-1');

    expect($body->toArray())->toBe([
        'runName' => 'run-2024-05',
        'datasetItemId' => 'di-1',
    ]);
});

it('serialises provided optionals and omits null ones', function () {
    $body = new CreateDatasetRunItemBody(
        runName: 'run-2024-05',
        datasetItemId: 'di-1',
        runDescription: 'first eval run',
        traceId: 'trace-abc',
    );

    expect($body->toArray())->toBe([
        'runName' => 'run-2024-05',
        'datasetItemId' => 'di-1',
        'runDescription' => 'first eval run',
        'traceId' => 'trace-abc',
    ]);
});
