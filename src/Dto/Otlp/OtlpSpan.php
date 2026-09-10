<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto\Otlp;

use Axyr\Langfuse\Contracts\SerializableInterface;

/**
 * One OTLP span. `kind` is always SPAN_KIND_INTERNAL (1) and `status.code` is
 * STATUS_CODE_ERROR (2) for failed observations, UNSET (0) otherwise.
 */
readonly class OtlpSpan implements SerializableInterface
{
    public const KIND_INTERNAL = 1;

    public const STATUS_UNSET = 0;

    public const STATUS_ERROR = 2;

    /**
     * @param array<int, OtlpAttribute> $attributes
     */
    public function __construct(
        public string $traceId,
        public string $spanId,
        public ?string $parentSpanId,
        public string $name,
        public string $startTimeUnixNano,
        public string $endTimeUnixNano,
        public array $attributes = [],
        public int $statusCode = self::STATUS_UNSET,
        public string $statusMessage = '',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $span = [
            'traceId' => $this->traceId,
            'spanId' => $this->spanId,
            'name' => $this->name,
            'kind' => self::KIND_INTERNAL,
            'startTimeUnixNano' => $this->startTimeUnixNano,
            'endTimeUnixNano' => $this->endTimeUnixNano,
            'attributes' => array_map(
                fn(OtlpAttribute $attribute): array => $attribute->toArray(),
                array_values($this->attributes),
            ),
            'status' => [
                'code' => $this->statusCode,
                'message' => $this->statusMessage,
            ],
        ];

        if ($this->parentSpanId !== null && $this->parentSpanId !== '') {
            $span['parentSpanId'] = $this->parentSpanId;
        }

        return $span;
    }
}
