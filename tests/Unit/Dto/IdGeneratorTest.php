<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\IdGenerator;

it('generates valid uuid v4 format', function () {
    $uuid = IdGenerator::uuid();

    expect($uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

it('generates unique uuids', function () {
    $uuids = array_map(fn() => IdGenerator::uuid(), range(1, 100));

    expect(array_unique($uuids))->toHaveCount(100);
});

it('generates valid iso 8601 zulu timestamp', function () {
    $timestamp = IdGenerator::timestamp();

    expect($timestamp)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/');
});

it('generates timestamps close to current time', function () {
    $before = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $timestamp = IdGenerator::timestamp();
    $after = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    $parsed = new DateTimeImmutable($timestamp);

    expect($parsed >= $before)->toBeTrue()
        ->and($parsed <= $after)->toBeTrue();
});
