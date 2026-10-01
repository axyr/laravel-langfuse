<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Api;

use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\OtelTraceApiClientInterface;
use Axyr\Langfuse\Dto\OtelExportResult;
use Axyr\Langfuse\Dto\Otlp\OtlpExportRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Posts OTLP/JSON to `/api/public/otel/v1/traces`.
 *
 * The `x-langfuse-ingestion-version: 4` header is what makes writes land in the
 * v4 model in real time; without it Langfuse can take 10-15 minutes to show the
 * data. v3 servers ignore the header.
 */
class OtelTraceApiClient implements OtelTraceApiClientInterface
{
    private const JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /** Statuses the OTLP spec says a client may retry. */
    private const RETRYABLE_STATUSES = [429, 502, 503, 504];

    public function __construct(
        private readonly LangfuseConfig $config,
    ) {}

    public function export(OtlpExportRequest $request): OtelExportResult
    {
        return $this->exportRaw($request->toArray());
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function exportRaw(array $payload): OtelExportResult
    {
        try {
            return $this->doExport($payload);
        } catch (\Throwable $throwable) {
            Log::warning('Langfuse OTLP export error', ['message' => $throwable->getMessage()]);

            return OtelExportResult::transportError($throwable->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function doExport(array $payload): OtelExportResult
    {
        $body = json_encode($payload, self::JSON_FLAGS);
        $headers = [
            'Authorization' => $this->config->authHeader(),
            'x-langfuse-ingestion-version' => '4',
        ];

        if ($this->config->compression) {
            $compressed = gzencode($body);

            if ($compressed !== false) {
                $body = $compressed;
                $headers['Content-Encoding'] = 'gzip';
            }
        }

        $response = Http::withHeaders($headers)
            ->timeout($this->config->requestTimeout)
            ->withBody($body, 'application/json')
            ->post($this->config->otelTracesUrl());

        return $response->successful()
            ? $this->readPartialSuccess($response)
            : $this->readFailure($response);
    }

    private function readPartialSuccess(Response $response): OtelExportResult
    {
        /** @var array<string, mixed>|null $partial */
        $partial = $response->json('partialSuccess');

        if (! is_array($partial) || $partial === []) {
            return OtelExportResult::success();
        }

        return $this->reportPartialSuccess($partial);
    }

    /**
     * @param array<string, mixed> $partial
     */
    private function reportPartialSuccess(array $partial): OtelExportResult
    {
        $rejected = is_int($partial['rejectedSpans'] ?? null) ? $partial['rejectedSpans'] : 0;
        $message = is_string($partial['errorMessage'] ?? null) ? $partial['errorMessage'] : null;

        Log::warning('Langfuse OTLP partial success', [
            'rejectedSpans' => $rejected,
            'errorMessage' => $message,
        ]);

        return OtelExportResult::partial($rejected, $message);
    }

    private function readFailure(Response $response): OtelExportResult
    {
        $status = $response->status();
        $retryable = in_array($status, self::RETRYABLE_STATUSES, true);

        Log::warning('Langfuse OTLP export failed', [
            'status' => $status,
            'retryable' => $retryable,
            'body' => $response->body(),
        ]);

        return OtelExportResult::failed(
            status: $status,
            retryable: $retryable,
            retryAfterSeconds: $this->retryAfter($response),
            errorMessage: $response->body(),
        );
    }

    private function retryAfter(Response $response): ?int
    {
        $header = $response->header('Retry-After');

        return ctype_digit($header) ? (int) $header : null;
    }
}
