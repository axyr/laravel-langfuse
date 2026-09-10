<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Otlp;

use Axyr\Langfuse\Dto\Otlp\OtlpAttribute;
use Illuminate\Support\Facades\Log;

/**
 * Turns PHP values into OTLP attribute values. Anything that is not a scalar is
 * JSON encoded into a `stringValue`; encoding failures fall back to readable
 * text, because a flush must never throw.
 */
class AttributeEncoder
{
    private const JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /**
     * Scalars keep their type, everything else becomes a JSON string.
     */
    public function scalar(string $key, mixed $value): ?OtlpAttribute
    {
        if ($value === null) {
            return null;
        }

        return $this->typed($key, $value);
    }

    /**
     * Free text: strings are passed through as-is, everything else is JSON encoded.
     */
    public function text(string $key, mixed $value): ?OtlpAttribute
    {
        if ($value === null) {
            return null;
        }

        return OtlpAttribute::string($key, is_string($value) ? $value : $this->json($value));
    }

    /**
     * @param array<int, mixed>|null $values
     */
    public function stringList(string $key, ?array $values): ?OtlpAttribute
    {
        if ($values === null || $values === []) {
            return null;
        }

        return OtlpAttribute::stringArray($key, array_map(
            fn(mixed $value): string => is_string($value) ? $value : $this->json($value),
            array_values($values),
        ));
    }

    /**
     * Only top-level keys are filterable in Langfuse; nested values are stored
     * as JSON strings.
     *
     * @param array<string, mixed>|null $metadata
     * @return array<string, OtlpAttribute>
     */
    public function metadata(string $prefix, ?array $metadata): array
    {
        $attributes = [];

        foreach ($metadata ?? [] as $key => $value) {
            $attribute = $this->scalar($prefix . $key, $value);

            if ($attribute !== null) {
                $attributes[$attribute->key] = $attribute;
            }
        }

        return $attributes;
    }

    /**
     * Drops the nulls and keys the rest by attribute name, so later attributes
     * can override earlier ones.
     *
     * @param array<int, OtlpAttribute|null> $attributes
     * @return array<string, OtlpAttribute>
     */
    public function keyed(array $attributes): array
    {
        $keyed = [];

        foreach ($attributes as $attribute) {
            if ($attribute !== null) {
                $keyed[$attribute->key] = $attribute;
            }
        }

        return $keyed;
    }

    public function json(mixed $value): string
    {
        try {
            return json_encode($value, self::JSON_FLAGS);
        } catch (\Throwable $throwable) {
            Log::warning('Langfuse attribute encoding failed', ['message' => $throwable->getMessage()]);

            return print_r($value, true);
        }
    }

    private function typed(string $key, mixed $value): OtlpAttribute
    {
        if (is_string($value)) {
            return OtlpAttribute::string($key, $value);
        }

        if (is_bool($value)) {
            return OtlpAttribute::bool($key, $value);
        }

        return $this->numeric($key, $value);
    }

    private function numeric(string $key, mixed $value): OtlpAttribute
    {
        if (is_int($value)) {
            return OtlpAttribute::int($key, $value);
        }

        if (is_float($value)) {
            return OtlpAttribute::double($key, $value);
        }

        return OtlpAttribute::string($key, $this->json($value));
    }
}
