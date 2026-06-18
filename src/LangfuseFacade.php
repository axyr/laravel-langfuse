<?php

declare(strict_types=1);

namespace Axyr\Langfuse;

use Axyr\Langfuse\Contracts\LangfuseClientInterface;
use Axyr\Langfuse\Testing\LangfuseFake;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Axyr\Langfuse\Objects\LangfuseTrace trace(\Axyr\Langfuse\Dto\TraceBody $body)
 * @method static \Axyr\Langfuse\Objects\LangfuseTrace currentTrace()
 * @method static void setCurrentTrace(\Axyr\Langfuse\Objects\LangfuseTrace $trace)
 * @method static void score(\Axyr\Langfuse\Dto\ScoreBody $body)
 * @method static \Axyr\Langfuse\Dto\ScoreResponse|null getScore(string $scoreId)
 * @method static \Axyr\Langfuse\Dto\ScoreListResponse|null getScores(?\Axyr\Langfuse\Dto\ScoreQuery $query = null)
 * @method static bool deleteScore(string $scoreId)
 * @method static \Axyr\Langfuse\Dto\ObservationResponse|null getObservation(string $observationId)
 * @method static \Axyr\Langfuse\Dto\ObservationListResponse|null getObservations(?\Axyr\Langfuse\Dto\ObservationQuery $query = null)
 * @method static \Axyr\Langfuse\Dto\MetricsResponse|null queryMetrics(\Axyr\Langfuse\Dto\MetricQuery $query)
 * @method static \Axyr\Langfuse\Dto\DatasetResponse|null getDataset(string $datasetName)
 * @method static \Axyr\Langfuse\Dto\DatasetListResponse|null listDatasets(?int $page = null, ?int $limit = null)
 * @method static \Axyr\Langfuse\Dto\DatasetResponse|null createDataset(\Axyr\Langfuse\Dto\CreateDatasetBody $body)
 * @method static void flush()
 * @method static bool isEnabled()
 * @method static \Axyr\Langfuse\Contracts\PromptInterface prompt(string $name, ?int $version = null, ?string $label = null, string|array<int, array<string, string>>|null $fallback = null)
 *
 * @see \Axyr\Langfuse\LangfuseClient
 */
class LangfuseFacade extends Facade
{
    public static function fake(): LangfuseFake
    {
        $fake = new LangfuseFake();
        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return LangfuseClientInterface::class;
    }
}
