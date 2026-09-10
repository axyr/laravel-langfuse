<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\IdGenerator;

it('generates valid uuid v4 format for scores and ingestion envelopes', function () {
    $uuid = IdGenerator::uuid();

    expect($uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

it('generates unique uuids', function () {
    $uuids = array_map(fn() => IdGenerator::uuid(), range(1, 100));

    expect(array_unique($uuids))->toHaveCount(100);
});

it('generates a 32 hex character trace id', function () {
    expect(IdGenerator::traceId())->toMatch('/^[0-9a-f]{32}$/');
});

it('generates a 16 hex character observation id', function () {
    expect(IdGenerator::spanId())->toMatch('/^[0-9a-f]{16}$/');
});

it('generates unique trace and span ids', function () {
    $traceIds = array_map(fn() => IdGenerator::traceId(), range(1, 100));
    $spanIds = array_map(fn() => IdGenerator::spanId(), range(1, 100));

    expect(array_unique($traceIds))->toHaveCount(100)
        ->and(array_unique($spanIds))->toHaveCount(100);
});

it('derives stable ids from a seed', function () {
    expect(IdGenerator::traceIdFromSeed('order-42'))->toBe(IdGenerator::traceIdFromSeed('order-42'))
        ->and(IdGenerator::traceIdFromSeed('order-42'))->toMatch('/^[0-9a-f]{32}$/')
        ->and(IdGenerator::spanIdFromSeed('order-42'))->toBe(IdGenerator::spanIdFromSeed('order-42'))
        ->and(IdGenerator::spanIdFromSeed('order-42'))->toMatch('/^[0-9a-f]{16}$/')
        ->and(IdGenerator::traceIdFromSeed('order-42'))->not->toBe(IdGenerator::traceIdFromSeed('order-43'));
});

it('seeds are the first hex characters of the sha256 of the seed', function () {
    $hash = hash('sha256', 'order-42');

    expect(IdGenerator::traceIdFromSeed('order-42'))->toBe(substr($hash, 0, 32))
        ->and(IdGenerator::spanIdFromSeed('order-42'))->toBe(substr($hash, 0, 16));
});

it('generates a trace id when none is given', function () {
    expect(IdGenerator::normalizeTraceId(null))->toMatch('/^[0-9a-f]{32}$/')
        ->and(IdGenerator::normalizeTraceId(''))->toMatch('/^[0-9a-f]{32}$/');
});

it('keeps a 32 hex trace id and lowercases it', function () {
    $id = strtoupper(str_repeat('ab', 16));

    expect(IdGenerator::normalizeTraceId($id))->toBe(str_repeat('ab', 16));
});

it('strips the dashes from a uuid trace id', function () {
    expect(IdGenerator::normalizeTraceId('0192F1B4-2C7E-7A1B-8D3E-9F0A1B2C3D4E'))
        ->toBe('0192f1b42c7e7a1b8d3e9f0a1b2c3d4e');
});

it('hashes a 16 hex observation id into a trace id', function () {
    expect(IdGenerator::normalizeTraceId('0192f1b42c7e7a1b'))
        ->toBe(IdGenerator::traceIdFromSeed('0192f1b42c7e7a1b'));
});

it('hashes any other trace id', function () {
    expect(IdGenerator::normalizeTraceId('trace-1'))->toBe(IdGenerator::traceIdFromSeed('trace-1'));
});

it('generates an observation id when none is given', function () {
    expect(IdGenerator::normalizeObservationId(null))->toMatch('/^[0-9a-f]{16}$/')
        ->and(IdGenerator::normalizeObservationId(''))->toMatch('/^[0-9a-f]{16}$/');
});

it('keeps a 16 hex observation id and lowercases it', function () {
    expect(IdGenerator::normalizeObservationId('0192F1B42C7E7A1B'))->toBe('0192f1b42c7e7a1b');
});

it('hashes a 32 hex trace id and a uuid into an observation id', function () {
    $traceId = str_repeat('ab', 16);

    expect(IdGenerator::normalizeObservationId($traceId))->toBe(IdGenerator::spanIdFromSeed($traceId))
        ->and(IdGenerator::normalizeObservationId('0192f1b4-2c7e-7a1b-8d3e-9f0a1b2c3d4e'))
        ->toBe(IdGenerator::spanIdFromSeed('0192f1b4-2c7e-7a1b-8d3e-9f0a1b2c3d4e'));
});

it('hashes any other observation id', function () {
    expect(IdGenerator::normalizeObservationId('span-1'))->toBe(IdGenerator::spanIdFromSeed('span-1'));
});

it('normalisation is idempotent', function () {
    $traceId = IdGenerator::normalizeTraceId('trace-1');
    $spanId = IdGenerator::normalizeObservationId('span-1');

    expect(IdGenerator::normalizeTraceId($traceId))->toBe($traceId)
        ->and(IdGenerator::normalizeObservationId($spanId))->toBe($spanId);
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
