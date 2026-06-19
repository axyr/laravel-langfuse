[Back to documentation](README.md)

# Querying (read API)

Read evaluation data back out of Langfuse: scores, observations, metrics, and the
full datasets → runs → run-items evaluation workflow. Every method here calls the
Langfuse public REST API (never the database).

All read methods are **resilient**: on a network error or non-2xx response they log a
warning and return `null`. They never throw into your application, so it is safe to
call them inline without `try`/`catch`. Always null-check the result.

A `null` return always means **the fetch failed** (network error or non-2xx). It is
distinct from a *successful* list query that simply has no matches - that returns a
list DTO whose `data` array is empty (not `null`). Consumers should treat the two
cases differently: retry/alert on `null`, treat empty `data` as "no results".

Examples use the `Langfuse` facade; the same methods exist on the injected
`Axyr\Langfuse\Contracts\LangfuseClientInterface`.

## Scores

```php
use Axyr\Langfuse\Dto\ScoreQuery;

// A single score by id
$score = Langfuse::getScore('score-abc');

if ($score !== null) {
    echo $score->name;        // 'accuracy'
    echo $score->value;       // 0.95 (float|null)
    echo $score->dataType;    // 'NUMERIC' | 'BOOLEAN' | 'CATEGORICAL' | 'CORRECTION'
    echo $score->stringValue; // for boolean/categorical scores
}

// Many scores, filtered. All ScoreQuery fields are optional.
$response = Langfuse::getScores(new ScoreQuery(
    datasetRunId: 'run-789',
    name: 'accuracy',
    source: 'EVAL',
    dataType: 'NUMERIC',
    fromTimestamp: '2026-01-01T00:00:00Z',
    page: 1,
    limit: 50,
));

if ($response !== null) {
    foreach ($response->data as $score) {
        // $score is a ScoreResponse
    }

    $response->meta->totalItems; // page-based pagination
    $response->meta->totalPages;
    $response->meta->page;
    $response->meta->limit;
}
```

`ScoreResponse` exposes `id`, `name`, `value`, `stringValue`, `dataType`, `source`,
`timestamp`, `createdAt`, `updatedAt`, `environment`, `traceId`, `sessionId`,
`observationId`, `datasetRunId`, `authorUserId`, `comment`, `configId`, `queueId`,
`metadata`, and (on list results) an optional `trace` (`userId`, `tags`,
`environment`, `sessionId`).

> `dataType` and `source` are returned as raw strings so values such as
> `CORRECTION` survive even though they are not part of the write-side
> `ScoreDataType` enum.

## Observations

The single-observation endpoint returns the full view; the list endpoint uses
**cursor-based** pagination and field-group selection.

```php
use Axyr\Langfuse\Dto\ObservationQuery;

// A single observation (event, span, or generation)
$observation = Langfuse::getObservation('obs-1');

if ($observation !== null) {
    $observation->type;          // 'GENERATION', 'SPAN', 'EVENT', ...
    $observation->level;         // ?ObservationLevel enum (DEBUG/DEFAULT/WARNING/ERROR)
    $observation->model;         // resolved model name
    $observation->usageDetails;  // ['input' => 10, 'output' => 5, 'total' => 15]
    $observation->costDetails;   // ['total' => 0.003, ...]
    $observation->latency;       // seconds
}

// Many observations with field selection and cursor pagination
$response = Langfuse::getObservations(new ObservationQuery(
    type: 'GENERATION',
    traceId: 'trace-123',
    fields: 'core,basic,usage,model',   // field groups to include
    limit: 100,
));

if ($response !== null) {
    foreach ($response->data as $observation) {
        // ObservationResponse
    }

    // Fetch the next page with the returned cursor
    $next = $response->meta->cursor;
    if ($next !== null) {
        Langfuse::getObservations(new ObservationQuery(cursor: $next));
    }
}
```

Available `fields` groups: `core`, `basic`, `time`, `io`, `metadata`, `model`,
`usage`, `prompt`, `metrics`. If omitted, `core` and `basic` are returned.

## Metrics / Query API

`queryMetrics()` runs a structured analytics query. This is the path for
trace-level analytics - there is no traces-list endpoint. The query targets the v1
`/api/public/metrics` endpoint, which supports the `traces`, `observations`,
`scores-numeric`, and `scores-categorical` views.

```php
use Axyr\Langfuse\Dto\MetricQuery;

$response = Langfuse::queryMetrics(new MetricQuery(
    view: 'traces',
    metrics: [
        ['measure' => 'count', 'aggregation' => 'count'],
    ],
    fromTimestamp: '2026-01-01T00:00:00Z',
    toTimestamp: '2026-02-01T00:00:00Z',
    dimensions: [
        ['field' => 'name'],
    ],
    filters: [
        ['column' => 'userId', 'operator' => '=', 'value' => 'u1', 'type' => 'string'],
    ],
    timeDimensionGranularity: 'day',   // optional, groups results by time bucket
    orderBy: [
        ['field' => 'count', 'direction' => 'desc'],
    ],
    configRowLimit: 100,
));

if ($response !== null) {
    foreach ($response->data as $row) {
        // Each row is an associative array of the requested dimensions
        // and metric values, e.g. ['name' => 'chat', 'count_count' => 42].
        // Histogram measures return [lower, upper, height] tuples.
    }
}
```

## Datasets

```php
use Axyr\Langfuse\Dto\CreateDatasetBody;

$dataset = Langfuse::getDataset('qa-eval');

$list = Langfuse::listDatasets(page: 1, limit: 50);

$created = Langfuse::createDataset(new CreateDatasetBody(
    name: 'qa-eval',
    description: 'QA evaluation set',
    metadata: ['owner' => 'team-a'],
));
```

`DatasetResponse` exposes `id`, `name`, `description`, `metadata`, `inputSchema`,
`expectedOutputSchema`, `projectId`, `createdAt`, `updatedAt`.

## Dataset items

```php
use Axyr\Langfuse\Dto\CreateDatasetItemBody;
use Axyr\Langfuse\Dto\DatasetItemQuery;
use Axyr\Langfuse\Enums\DatasetStatus;

$item = Langfuse::getDatasetItem('di-1');

$items = Langfuse::listDatasetItems(new DatasetItemQuery(
    datasetName: 'qa-eval',
    page: 1,
    limit: 50,
));

$created = Langfuse::createDatasetItem(new CreateDatasetItemBody(
    datasetName: 'qa-eval',
    input: ['question' => 'What is 2+2?'],
    expectedOutput: ['answer' => '4'],
    status: DatasetStatus::ACTIVE,
));

Langfuse::deleteDatasetItem('di-1'); // returns bool
```

## Dataset runs and run items

A run groups the results of executing your pipeline over a dataset. Each run item
links one dataset item to the trace (and optionally observation) produced for it.

```php
use Axyr\Langfuse\Dto\CreateDatasetRunItemBody;

// Link a produced trace to a dataset item in a run (creates the run if needed)
Langfuse::createDatasetRunItem(new CreateDatasetRunItemBody(
    runName: 'run-2026-01',
    datasetItemId: 'di-1',
    traceId: $trace->getId(),
));

// Read a run back with all of its run items
$run = Langfuse::getDatasetRun('qa-eval', 'run-2026-01');

if ($run !== null) {
    $run->run->name;                 // DatasetRunResponse
    foreach ($run->datasetRunItems as $runItem) {
        $runItem->datasetItemId;
        $runItem->traceId;
    }
}

// List runs for a dataset
$runs = Langfuse::listDatasetRuns('qa-eval', page: 1, limit: 50);

// List run items (note: filtered by dataset id, not name)
$runItems = Langfuse::listDatasetRunItems('ds-1', 'run-2026-01', page: 1, limit: 50);

Langfuse::deleteDatasetRun('qa-eval', 'run-2026-01'); // returns bool
```

## Evaluation harness

A typical external evaluation app (for example a RAG quality harness) uses the
write side to produce traces and the read side to pull results back for a run and
snapshot them:

```php
use Axyr\Langfuse\Dto\CreateDatasetRunItemBody;
use Axyr\Langfuse\Dto\MetricQuery;
use Axyr\Langfuse\Dto\ScoreQuery;

$datasetName = 'qa-eval';
$runName = 'run-2026-01';

// 1. Execute the pipeline over each dataset item, tracing each call (write side),
//    and register the produced trace against the run.
foreach (Langfuse::listDatasetItems(new \Axyr\Langfuse\Dto\DatasetItemQuery(
    datasetName: $datasetName,
))?->data ?? [] as $item) {
    $trace = Langfuse::trace(/* ... run your pipeline, traced ... */);

    Langfuse::createDatasetRunItem(new CreateDatasetRunItemBody(
        runName: $runName,
        datasetItemId: $item->id,
        traceId: $trace->getId(),
    ));
}

Langfuse::flush(); // ensure traces/scores are sent before reading back

// 2. Pull the scores produced for this run.
$scores = Langfuse::getScores(new ScoreQuery(datasetRunId: $runName));

// 3. Pull aggregate metrics for the run window.
$metrics = Langfuse::queryMetrics(new MetricQuery(
    view: 'scores-numeric',
    metrics: [['measure' => 'value', 'aggregation' => 'avg']],
    fromTimestamp: '2026-01-01T00:00:00Z',
    toTimestamp: '2026-02-01T00:00:00Z',
    dimensions: [['field' => 'name']],
));

// 4. Snapshot $scores and $metrics into your own storage and build the report.
```

## Testing

`Langfuse::fake()` returns a `LangfuseFake` you can seed and assert against, so read
calls work in tests without hitting the API:

```php
use Axyr\Langfuse\Dto\ScoreResponse;

$fake = Langfuse::fake();
$fake->withScore(ScoreResponse::fromArray([
    'id' => 'score-abc',
    'name' => 'accuracy',
    'dataType' => 'NUMERIC',
    'value' => 0.95,
]));

expect(Langfuse::getScore('score-abc')?->value)->toBe(0.95);
```

Seeders: `withScore()`, `withObservation()`, `withMetrics()`, `withDataset()`,
`withDatasetItem()`, `withDatasetRun()`. Creation assertions: `assertDatasetCreated()`,
`assertDatasetItemCreated()`, `assertDatasetRunItemCreated()`. See [Testing](testing.md).
