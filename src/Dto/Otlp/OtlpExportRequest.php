<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto\Otlp;

use Axyr\Langfuse\Contracts\SerializableInterface;

/**
 * An OTLP `ExportTraceServiceRequest`: the body posted to
 * `/api/public/otel/v1/traces`. Langfuse stores the resource attributes under
 * `metadata.resourceAttributes`.
 */
readonly class OtlpExportRequest implements SerializableInterface
{
    /**
     * @param array<int, OtlpSpan> $spans
     * @param array<int, OtlpAttribute> $resourceAttributes
     */
    public function __construct(
        public array $spans,
        public array $resourceAttributes,
        public string $scopeName,
        public string $scopeVersion,
    ) {}

    public function spanCount(): int
    {
        return count($this->spans);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'resourceSpans' => [[
                'resource' => [
                    'attributes' => array_map(
                        fn(OtlpAttribute $attribute): array => $attribute->toArray(),
                        array_values($this->resourceAttributes),
                    ),
                ],
                'scopeSpans' => [[
                    'scope' => [
                        'name' => $this->scopeName,
                        'version' => $this->scopeVersion,
                    ],
                    'spans' => array_map(
                        fn(OtlpSpan $span): array => $span->toArray(),
                        array_values($this->spans),
                    ),
                ]],
            ]],
        ];
    }
}
