<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Contracts;

use Axyr\Langfuse\Dto\OtelExportResult;
use Axyr\Langfuse\Dto\Otlp\OtlpExportRequest;

interface OtelTraceApiClientInterface
{
    public function export(OtlpExportRequest $request): OtelExportResult;

    /**
     * @param array<string, mixed> $payload
     */
    public function exportRaw(array $payload): OtelExportResult;
}
