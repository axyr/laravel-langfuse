<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

/**
 * OTLP carries span edges as `startTimeUnixNano` / `endTimeUnixNano`: 64-bit
 * nanosecond counts encoded as decimal strings in OTLP/JSON. The package keeps
 * ISO 8601 strings on its public surface and converts here, at serialisation.
 *
 * Precision stays at microseconds, which is what PHP can observe.
 */
readonly class Timestamp
{
    /**
     * Returns null for input that cannot be parsed; callers substitute the
     * current time and log, because a flush must never throw.
     */
    public static function toUnixNano(string $iso): ?string
    {
        try {
            $moment = new \DateTimeImmutable($iso);
        } catch (\Exception) {
            return null;
        }

        return self::fromDateTime($moment);
    }

    public static function nowUnixNano(): string
    {
        return self::fromDateTime(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
    }

    private static function fromDateTime(\DateTimeImmutable $moment): string
    {
        $seconds = (int) $moment->format('U');
        $microseconds = (int) $moment->format('u');

        return (string) ($seconds * 1_000_000_000 + $microseconds * 1_000);
    }
}
