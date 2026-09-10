[Back to documentation](README.md)

# Querying (read API)

Read evaluation data back out of Langfuse: scores, observations, metrics, and the
full datasets → experiments → experiment-items evaluation workflow. Every method
here calls the Langfuse public REST API (never the database).

> **These reads need Langfuse v4.** Observations v2, Metrics v2 and the Experiments
> API only exist there. Scores v3 also works on Langfuse v3. There is no fallback to
> the v1 endpoints: Langfuse Cloud removes them on 2026-11-16.

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

Reads go to `GET /api/public/v3/scores`, which uses cursor pagination and
comma-separated list filters.

```php
use Axyr\Langfuse\Dto\ScoreQuery;

// A single score by id
$score = Langfuse::getScore('score-abc');

if ($score !== null) {
    echo $score->name;      // 'accuracy'
    $score->value;          // float|bool|string|null, depending on dataType
    echo $score->dataType;  // 'NUMERIC' | 'BOOLEAN' | 'CATEGORICAL' | 'TEXT' | 'CORRECTION'
}

// Many scores, filtered. All ScoreQuery fields are optional.
$response = Langfuse::getScores(new ScoreQuery(
    experimentId: ['nightly-eval'],
    name: ['accuracy'],
    source: ['EVAL'],
    dataType: ['NUMERIC'],
    fromTimestamp: '2026-01-01T00:00:00Z',
    fields: 'details,subject',
    limit: 50,
));

if ($response !== null) {
    foreach ($response->data as $score) {
        // $score is a ScoreResponse
    }

    // Cursor pagination: the cursor is absent on the last page
    if ($response->meta->hasMore()) {
        Langfuse::getScores(new ScoreQuery(cursor: $response->meta->cursor));
    }
}
```

`ScoreResponse` exposes `id`, `projectId`, `name`, `value`, `dataType`, `source`,
`timestamp`, `createdAt`, `updatedAt`, `environment`, plus `comment`, `configId`,
`metadata` (`fields=details`), `subject` (`fields=subject`) and `authorUserId`,
`queueId` (`fields=annotation`). `getScore()` requests all three groups.

**`value` is polymorphic** in v3: a number for `NUMERIC`, a real boolean for
`BOOLEAN`, a string for `CATEGORICAL`, `TEXT` and `CORRECTION`. There is no
`stringValue`.

**What a score is attached to** lives in `subject` (`kind`, `id`, and `traceId` for
observation subjects). Convenience accessors keep call sites short:

```php
$score->traceId();       // trace subjects, and the parent trace of observation subjects
$score->observationId();
$score->sessionId();
$score->experimentId();
```

Filter rules worth knowing: `traceId`, `sessionId` and `experimentId` are mutually
exclusive; `observationId` requires `traceId`; `value`, `valueMin` and `valueMax`
require a single `dataType`; `limit` above 100 returns a 400.

> `dataType` and `source` are returned as raw strings so values the package does not
> know yet survive.

## Observations

`GET /api/public/v2/observations` uses cursor-based pagination and field-group
selection. There is no get-by-id route any more: a single observation is an `id`
filter on the list, which `getObservation()` builds for you.

```php
use Axyr\Langfuse\Dto\ObservationQuery;

// A single observation (event, span, or generation)
$observation = Langfuse::getObservation(
    $generation->getId(),
    fromStartTime: '2026-01-01T00:00:00Z',
    toStartTime: '2026-01-02T00:00:00Z',
    fields: 'core,basic,io,usage',
);

if ($observation !== null) {
    $observation->type;              // 'GENERATION', 'SPAN', 'EVENT', 'TOOL', ...
    $observation->level;             // ?ObservationLevel enum (DEBUG/DEFAULT/WARNING/ERROR)
    $observation->model;             // resolved model name
    $observation->usageDetails;      // ['input' => 10, 'output' => 5, 'total' => 15]
    $observation->costDetails;       // ['total' => 0.003, ...]
    $observation->latency;           // seconds
    $observation->isRootObservation; // true for the trace's root span
    $observation->userId;            // trace-level attributes live on every row in v4
    $observation->traceName;
}
```

**Always pass a time window when you can.** The v4 tables are partitioned by time, so
a lookup without one scans everything. `getObservation()` falls back to the last 30
days when you do not pass `fromStartTime`.

```php
// Many observations with field selection and cursor pagination
$response = Langfuse::getObservations(new ObservationQuery(
    type: 'GENERATION',
    traceId: $trace->getId(),
    sessionId: 'session-abc',
    isRootObservation: false,
    fromStartTime: '2026-01-01T00:00:00Z',
    fields: 'core,basic,usage,model,trace_context',
    limit: 100,
));

if ($response !== null) {
    foreach ($response->data as $observation) {
        // ObservationResponse
    }

    $next = $response->meta->cursor;
    if ($next !== null) {
        Langfuse::getObservations(new ObservationQuery(cursor: $next));
    }
}
```

Available `fields` groups: `core`, `basic`, `time`, `io`, `metadata`, `model`,
`usage`, `prompt`, `metrics`, `trace_context`. If omitted, `core` and `basic` are
returned. `input` and `output` always come back as raw strings.

v4 adds `AGENT`, `TOOL`, `CHAIN`, `RETRIEVER`, `EVALUATOR`, `EMBEDDING` and
`GUARDRAIL` to `type`; the package passes them through as strings.

## Metrics / Query API

`queryMetrics()` runs a structured analytics query against
`GET /api/public/v2/metrics`.

**The v1 `traces` view is gone.** Trace-level numbers now come from the
`observations` view filtered - or grouped - on `isRootObservation`, because in v4 a
trace is the set of rows sharing a trace id and its root observation carries the
trace-level attributes.

Views: `observations`, `scores-numeric`, `scores-boolean`, `scores-categorical`.
`MetricQuery` rejects anything else at construction with an
`InvalidArgumentException`, so a leftover `'traces'` query fails fast with a clear
message instead of a logged 400.

```php
use Axyr\Langfuse\Dto\MetricQuery;

$response = Langfuse::queryMetrics(new MetricQuery(
    view: 'observations',
    metrics: [
        ['measure' => 'count', 'aggregation' => 'count'],
    ],
    fromTimestamp: '2026-01-01T00:00:00Z',
    toTimestamp: '2026-02-01T00:00:00Z',
    dimensions: [
        ['field' => 'traceName'],
    ],
    filters: [
        ['column' => 'isRootObservation', 'operator' => '=', 'value' => 'true', 'type' => 'boolean'],
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
        // and metric values, e.g. ['traceName' => 'chat', 'count_count' => 42].
        // Histogram measures return [lower, upper, height] tuples.
    }
}
```

New observation dimensions in v2: `tags`, `release`, `isRootObservation`,
`startTimeMonth`, `providedModelName`, `promptName`, `promptVersion`, alongside the
backwards-compatible `traceName`, `traceRelease` and `traceVersion`.

**High-cardinality dimensions return a 400 when used for grouping**: `id`, `traceId`,
`userId`, `sessionId`, `parentObservationId` on the observations view, and `id`,
`traceId`, `userId`, `sessionId`, `observationId` on the score views. They stay
usable in filters.

Aggregations: `sum`, `avg`, `count`, `max`, `min`, `p50`, `p75`, `p90`, `p95`, `p99`,
`histogram`. Granularities: `auto`, `minute`, `hour`, `day`, `week`, `month`.

> **Rate limit.** Metrics v2 on Langfuse Cloud is 100 requests per **day** on Hobby,
> 100 per hour on Core and 500 per hour on Pro and up. Call it once per run and
> snapshot the result; do not poll it.

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

## Experiments

Experiments replace dataset runs in v4. There is no "create experiment" call and no
`dataset-run-items` endpoint: **an experiment is created implicitly by tracing**.
Attach an `ExperimentContext` to the trace of each item and Langfuse assembles the
experiment from the attributes those traces carry.

### Writing an experiment item

```php
use Axyr\Langfuse\Dto\ExperimentContext;
use Axyr\Langfuse\Dto\ExperimentItemContext;
use Axyr\Langfuse\Dto\TraceBody;

$experiment = ExperimentContext::named('run-2026-01', datasetId: 'ds-1');

$trace = Langfuse::trace((new TraceBody(
    name: 'qa-eval',
    input: $item->input,
    environment: 'experiment',
))->forExperimentItem($experiment, new ExperimentItemContext(
    itemId: $item->id,
    expectedOutput: $item->expectedOutput,
)));

// ... run the pipeline, traced ...

$trace->end(output: $answer);
```

Both the experiment `id` and `name` must be unique per experiment;
`ExperimentContext::named()` derives the id from the name for you, or pass both
explicitly with the constructor. Input **and** output are required for an item, so
set `TraceBody::$input` and pass the output to `end()`.

To score an item, use the item trace's ids:

```php
Langfuse::score(new ScoreBody(
    name: 'accuracy',
    traceId: $trace->getId(),
    observationId: $trace->getRootObservationId(),
    value: 1.0,
));
```

For an experiment-level score, use `datasetRunId: 'run-2026-01'` instead - the field
kept its v2 name but now holds the experiment id.

### Reading experiments back

Both endpoints require a `fromStartTime` window and use cursor pagination.

```php
use Axyr\Langfuse\Dto\ExperimentItemQuery;
use Axyr\Langfuse\Dto\ExperimentQuery;

$experiments = Langfuse::listExperiments(new ExperimentQuery(
    fromStartTime: '2026-01-01T00:00:00Z',
    toStartTime: '2026-02-01T00:00:00Z',
    datasetId: ['ds-1'],
    fields: 'core,metadata,scores',
    limit: 50,
));

foreach ($experiments?->data ?? [] as $experiment) {
    $experiment->id;
    $experiment->name;
    $experiment->itemCount;
    $experiment->scores;      // ScoreResponse[] with fields=scores
}

// One experiment: a filtered list that returns the first row
$experiment = Langfuse::getExperiment('run-2026-01', '2026-01-01T00:00:00Z');

$items = Langfuse::listExperimentItems(new ExperimentItemQuery(
    fromStartTime: '2026-01-01T00:00:00Z',
    experimentId: ['run-2026-01'],
    fields: 'core,dataset,io,scores',
    limit: 50,
));

foreach ($items?->data ?? [] as $item) {
    $item->id;               // the root observation id of the item's trace
    $item->traceId;
    $item->experimentItemId; // the dataset item id
    $item->input;
    $item->output;
    $item->expectedOutput;
    $item->scores;
}
```

`ExperimentQuery` field groups: `core` (default), `metadata`, `scores`.
`ExperimentItemQuery` field groups: `core`, `dataset` (both default), `io`,
`metadata`, `itemMetadata`, `experimentMetadata`, `scores`.

> **There is no delete.** v4 has no endpoint that removes an experiment. The only way
> to remove its data is `DELETE /api/public/traces` with the item trace ids, which
> this package does not wrap: trace deletion is rate limited to 50 requests per day
> on Hobby and 1,000 on Pro, so it is a deliberate manual operation. Prefer starting a
> new experiment over deleting an old one.

## Evaluation harness

A typical external evaluation app (for example a RAG quality harness) uses the
write side to produce item traces and the read side to pull results back:

```php
use Axyr\Langfuse\Dto\DatasetItemQuery;
use Axyr\Langfuse\Dto\ExperimentContext;
use Axyr\Langfuse\Dto\ExperimentItemContext;
use Axyr\Langfuse\Dto\ExperimentItemQuery;
use Axyr\Langfuse\Dto\MetricQuery;
use Axyr\Langfuse\Dto\ScoreQuery;
use Axyr\Langfuse\Dto\TraceBody;

$experiment = ExperimentContext::named('run-2026-01', datasetId: 'ds-1');
$startedAt = now()->toIso8601ZuluString();

// 1. Execute the pipeline over each dataset item, tracing each run as an
//    experiment item.
foreach (Langfuse::listDatasetItems(new DatasetItemQuery(datasetName: 'qa-eval'))?->data ?? [] as $item) {
    $trace = Langfuse::trace((new TraceBody(
        name: 'qa-eval',
        input: $item->input,
        environment: 'experiment',
    ))->forExperimentItem($experiment, new ExperimentItemContext(
        itemId: $item->id,
        expectedOutput: $item->expectedOutput,
    )));

    $answer = /* ... run your pipeline, traced under $trace ... */ '';

    $trace->end(output: $answer);
}

Langfuse::shutdown(); // end anything still open and flush before reading back

// 2. Pull the scores produced for this experiment.
$scores = Langfuse::getScores(new ScoreQuery(experimentId: [$experiment->id]));

// 3. Pull the items with their inputs, outputs and scores.
$items = Langfuse::listExperimentItems(new ExperimentItemQuery(
    fromStartTime: $startedAt,
    experimentId: [$experiment->id],
    fields: 'core,dataset,io,scores',
));

// 4. Pull aggregate metrics for the run window (one call - see the rate limit).
$metrics = Langfuse::queryMetrics(new MetricQuery(
    view: 'scores-numeric',
    metrics: [['measure' => 'value', 'aggregation' => 'avg']],
    fromTimestamp: $startedAt,
    toTimestamp: now()->toIso8601ZuluString(),
    dimensions: [['field' => 'name']],
));

// 5. Snapshot $items, $scores and $metrics into your own storage and build the report.
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
`withDatasetItem()`, `withExperiment()`, `withExperimentItem()`. Creation
assertions: `assertDatasetCreated()`, `assertDatasetItemCreated()`,
`assertExperimentItemTraced()`. See [Testing](testing.md).
