<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto\Otlp;

use Axyr\Langfuse\Contracts\SerializableInterface;

/**
 * One OTLP key/value attribute. The value is already encoded in the OTLP/JSON
 * shape (`stringValue`, `intValue`, `doubleValue`, `boolValue`, `arrayValue`).
 */
readonly class OtlpAttribute implements SerializableInterface
{
    /**
     * @param array<string, mixed> $value
     */
    private function __construct(
        public string $key,
        public array $value,
    ) {}

    public static function string(string $key, string $value): self
    {
        return new self($key, ['stringValue' => $value]);
    }

    public static function int(string $key, int $value): self
    {
        return new self($key, ['intValue' => $value]);
    }

    public static function double(string $key, float $value): self
    {
        return new self($key, ['doubleValue' => $value]);
    }

    public static function bool(string $key, bool $value): self
    {
        return new self($key, ['boolValue' => $value]);
    }

    /**
     * @param array<int, string> $values
     */
    public static function stringArray(string $key, array $values): self
    {
        return new self($key, [
            'arrayValue' => [
                'values' => array_map(
                    fn(string $value): array => ['stringValue' => $value],
                    array_values($values),
                ),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
        ];
    }
}
