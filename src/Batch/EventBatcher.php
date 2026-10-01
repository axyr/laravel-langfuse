<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Batch;

use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\OtelTraceApiClientInterface;
use Axyr\Langfuse\Contracts\ScoreApiClientInterface;
use Axyr\Langfuse\Dto\IngestionBatch;
use Axyr\Langfuse\Dto\Otlp\OtlpExportRequest;
use Axyr\Langfuse\Otlp\OtlpRequestFactory;

/**
 * Sends the batch inside the current process.
 */
class EventBatcher extends AbstractEventBatcher
{
    public function __construct(
        private readonly OtelTraceApiClientInterface $otelClient,
        private readonly ScoreApiClientInterface $scoreApiClient,
        LangfuseConfig $config,
        OtlpRequestFactory $requestFactory,
        ScoreBatchFactory $scoreBatchFactory,
    ) {
        parent::__construct($config, $requestFactory, $scoreBatchFactory);
    }

    protected function sendObservations(OtlpExportRequest $request): void
    {
        $this->otelClient->export($request);
    }

    protected function sendScores(IngestionBatch $batch): void
    {
        $this->scoreApiClient->ingest($batch);
    }
}
