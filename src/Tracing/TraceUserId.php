<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Tracing;

use Illuminate\Contracts\Auth\Authenticatable;
use Stringable;

/**
 * Turns whatever an application uses to identify a user (an authenticatable,
 * an Eloquent model, a plain object with an id, or a scalar key) into the
 * string Langfuse expects as a trace userId.
 */
final class TraceUserId
{
    public static function from(mixed $value): ?string
    {
        if ($value instanceof Authenticatable) {
            return self::fromScalar($value->getAuthIdentifier());
        }

        if (is_object($value)) {
            return self::fromScalar(self::keyOf($value));
        }

        return self::fromScalar($value);
    }

    private static function keyOf(object $value): mixed
    {
        if (method_exists($value, 'getKey')) {
            return $value->getKey();
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        return $value->id ?? null;
    }

    private static function fromScalar(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }
}
