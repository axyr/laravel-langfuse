<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

/**
 * OTLP identifies a span by a 16-byte trace id and an 8-byte span id, hex
 * encoded in OTLP/JSON: 32 hex characters for a trace id, 16 for an observation
 * id. Ids supplied by callers are normalised into that shape once, when the body
 * is constructed, so existing call sites keep working and keep linking to the
 * same observation.
 */
readonly class IdGenerator
{
    private const TRACE_ID_LENGTH = 32;

    private const OBSERVATION_ID_LENGTH = 16;

    /**
     * Free-form id, kept for scores and ingestion envelopes which are not OTLP ids.
     */
    public static function uuid(): string
    {
        $bytes = random_bytes(16);

        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);

        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(substr($bytes, 0, 4)),
            bin2hex(substr($bytes, 4, 2)),
            bin2hex(substr($bytes, 6, 2)),
            bin2hex(substr($bytes, 8, 2)),
            bin2hex(substr($bytes, 10, 6)),
        );
    }

    public static function traceId(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function spanId(): string
    {
        return bin2hex(random_bytes(8));
    }

    public static function traceIdFromSeed(string $seed): string
    {
        return substr(hash('sha256', $seed), 0, self::TRACE_ID_LENGTH);
    }

    public static function spanIdFromSeed(string $seed): string
    {
        return substr(hash('sha256', $seed), 0, self::OBSERVATION_ID_LENGTH);
    }

    /**
     * A 32 hex id is kept, a UUID loses its dashes, anything else is hashed.
     */
    public static function normalizeTraceId(?string $id): string
    {
        if ($id === null || $id === '') {
            return self::traceId();
        }

        return self::asTraceId($id) ?? self::traceIdFromSeed($id);
    }

    private static function asTraceId(string $id): ?string
    {
        if (self::isHex($id, self::TRACE_ID_LENGTH)) {
            return strtolower($id);
        }

        $undashed = str_replace('-', '', $id);

        return self::isHex($undashed, self::TRACE_ID_LENGTH) ? strtolower($undashed) : null;
    }

    /**
     * A 16 hex id is kept, anything else (including a 32 hex trace id or a UUID)
     * is hashed down to 16 hex characters.
     */
    public static function normalizeObservationId(?string $id): string
    {
        if ($id === null || $id === '') {
            return self::spanId();
        }

        if (self::isHex($id, self::OBSERVATION_ID_LENGTH)) {
            return strtolower($id);
        }

        return self::spanIdFromSeed($id);
    }

    private static function isHex(string $value, int $length): bool
    {
        return strlen($value) === $length && ctype_xdigit($value);
    }

    public static function timestamp(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->format('Y-m-d\TH:i:s.u\Z');
    }
}
