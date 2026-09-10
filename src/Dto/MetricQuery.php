<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

/**
 * Structured query for the Langfuse Metrics API (GET /api/public/v2/metrics).
 *
 * v2 views: `observations`, `scores-numeric`, `scores-boolean` and
 * `scores-categorical`. The v1 `traces` view is gone; use `observations` with a
 * filter or grouping on `isRootObservation` for trace-level numbers.
 *
 * Grouping on a high-cardinality dimension (`id`, `traceId`, `userId`,
 * `sessionId`, `parentObservationId`, `observationId`) returns 400; those stay
 * usable in filters.
 *
 * The query is serialised to a JSON string and passed as the `query` parameter.
 * `metrics`, `dimensions`, `filters` and `orderBy` are lists of associative
 * arrays whose shapes match the spec exactly, e.g.:
 *   metrics:    [['measure' => 'count', 'aggregation' => 'count']]
 *   dimensions: [['field' => 'name']]
 *   filters:    [['column' => 'userId', 'operator' => '=', 'value' => 'u1', 'type' => 'string']]
 *   orderBy:    [['field' => 'count', 'direction' => 'desc']]
 */
readonly class MetricQuery
{
    public const VIEWS = ['observations', 'scores-numeric', 'scores-boolean', 'scores-categorical'];

    /**
     * @param  array<int, array<string, mixed>>  $metrics
     * @param  array<int, array<string, mixed>>  $dimensions
     * @param  array<int, array<string, mixed>>  $filters
     * @param  array<int, array<string, mixed>>  $orderBy
     */
    public function __construct(
        public string $view,
        public array $metrics,
        public string $fromTimestamp,
        public string $toTimestamp,
        public array $dimensions = [],
        public array $filters = [],
        public ?string $timeDimensionGranularity = null,
        public array $orderBy = [],
        public ?int $configBins = null,
        public ?int $configRowLimit = null,
    ) {
        if (! in_array($view, self::VIEWS, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown Langfuse metrics view "%s". The v2 API accepts: %s.',
                $view,
                implode(', ', self::VIEWS),
            ));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $query = [
            'view' => $this->view,
            'metrics' => $this->metrics,
            'dimensions' => $this->dimensions,
            'filters' => $this->filters,
            'fromTimestamp' => $this->fromTimestamp,
            'toTimestamp' => $this->toTimestamp,
            'orderBy' => $this->orderBy,
        ];

        if ($this->timeDimensionGranularity !== null) {
            $query['timeDimension'] = ['granularity' => $this->timeDimensionGranularity];
        }

        $config = array_filter([
            'bins' => $this->configBins,
            'row_limit' => $this->configRowLimit,
        ], fn(mixed $value): bool => $value !== null);

        if ($config !== []) {
            $query['config'] = $config;
        }

        return array_filter($query, fn(mixed $value): bool => $value !== []);
    }
}
