<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\CreateDatasetBody;

it('serialises only the name when no optionals are set', function () {
    $body = new CreateDatasetBody(name: 'qa-eval');

    expect($body->toArray())->toBe(['name' => 'qa-eval']);
});

it('serialises provided optionals and omits null ones', function () {
    $body = new CreateDatasetBody(
        name: 'qa-eval',
        description: 'QA evaluation set',
        metadata: ['owner' => 'team-a'],
    );

    expect($body->toArray())->toBe([
        'name' => 'qa-eval',
        'description' => 'QA evaluation set',
        'metadata' => ['owner' => 'team-a'],
    ]);
});
