<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Otlp;

use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Dto\Otlp\OtlpAttribute;
use Axyr\Langfuse\Dto\Otlp\OtlpExportRequest;
use Axyr\Langfuse\Dto\Otlp\OtlpSpan;
use Axyr\Langfuse\Tracing\CompletedObservation;
use Axyr\Langfuse\Version;
use Illuminate\Support\Facades\Log;

/**
 * Builds the OTLP export requests for a batch. Langfuse caps an OTLP request at
 * 5 MB, so spans are split into requests that stay below 4 MB serialised.
 */
class OtlpRequestFactory
{
    public const MAX_REQUEST_BYTES = 4 * 1024 * 1024;

    public function __construct(
        private readonly LangfuseConfig $config,
        private readonly ObservationSerializer $serializer = new ObservationSerializer(),
    ) {}

    /**
     * @param array<int, CompletedObservation> $observations
     * @return array<int, OtlpExportRequest>
     */
    public function build(array $observations): array
    {
        $spans = array_map(
            fn(CompletedObservation $observation): OtlpSpan => $this->serializer->toSpan($observation),
            array_values($observations),
        );

        return array_map(
            fn(array $chunk): OtlpExportRequest => new OtlpExportRequest(
                spans: $chunk,
                resourceAttributes: $this->resourceAttributes(),
                scopeName: Version::SDK_NAME,
                scopeVersion: Version::SDK_VERSION,
            ),
            $this->split($spans),
        );
    }

    /**
     * @return array<int, OtlpAttribute>
     */
    public function resourceAttributes(): array
    {
        return [
            OtlpAttribute::string('service.name', $this->config->serviceName),
            OtlpAttribute::string('telemetry.sdk.name', Version::SDK_NAME),
            OtlpAttribute::string('telemetry.sdk.language', Version::SDK_LANGUAGE),
            OtlpAttribute::string('telemetry.sdk.version', Version::SDK_VERSION),
        ];
    }

    /**
     * @param array<int, OtlpSpan> $spans
     * @return array<int, array<int, OtlpSpan>>
     */
    private function split(array $spans): array
    {
        $chunks = [];
        $current = [];
        $size = 0;

        foreach ($spans as $span) {
            $spanSize = $this->sizeOf($span);

            if ($this->isFull($current, $size + $spanSize)) {
                $chunks[] = $current;
                $current = [];
                $size = 0;
            }

            $current[] = $span;
            $size += $spanSize;
        }

        return $current === [] ? $chunks : [...$chunks, $current];
    }

    /**
     * @param array<int, OtlpSpan> $current
     */
    private function isFull(array $current, int $size): bool
    {
        return $current !== [] && $size > self::MAX_REQUEST_BYTES;
    }

    /**
     * A single span above the limit is exported on its own and the server decides.
     */
    private function sizeOf(OtlpSpan $span): int
    {
        $encoded = json_encode($span->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $size = $encoded === false ? 0 : strlen($encoded);

        if ($size > self::MAX_REQUEST_BYTES) {
            Log::warning('Langfuse observation exceeds the OTLP request limit', [
                'spanId' => $span->spanId,
                'bytes' => $size,
            ]);
        }

        return $size;
    }
}
