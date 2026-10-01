<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\Timestamp;

it('converts an iso 8601 zulu timestamp to unix nanoseconds', function () {
    expect(Timestamp::toUnixNano('2026-09-09T12:34:56.123456Z'))->toBe('1788957296123456000');
});

it('converts the package timestamp format to unix nanoseconds', function () {
    $nanoseconds = Timestamp::toUnixNano('2024-01-01T00:00:00.000000Z');

    expect($nanoseconds)->toBe((string) (strtotime('2024-01-01T00:00:00Z') * 1_000_000_000));
});

it('keeps microsecond precision', function () {
    $nanoseconds = Timestamp::toUnixNano('2024-01-01T00:00:00.123456Z');

    expect($nanoseconds)->toBe((string) (strtotime('2024-01-01T00:00:00Z') * 1_000_000_000 + 123_456_000));
});

it('converts an offset timestamp to utc', function () {
    expect(Timestamp::toUnixNano('2024-01-01T02:00:00.000000+02:00'))
        ->toBe(Timestamp::toUnixNano('2024-01-01T00:00:00.000000Z'));
});

it('returns null for input it cannot parse', function () {
    expect(Timestamp::toUnixNano('not a timestamp'))->toBeNull();
});

it('produces the current time in nanoseconds', function () {
    $before = (int) (microtime(true) * 1_000_000) * 1_000;
    $now = Timestamp::nowUnixNano();
    $after = (int) ((microtime(true) + 1) * 1_000_000) * 1_000;

    expect($now)->toMatch('/^\d+$/')
        ->and((int) $now)->toBeGreaterThanOrEqual($before - 1_000_000_000)
        ->and((int) $now)->toBeLessThanOrEqual($after);
});
