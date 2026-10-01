[Back to documentation](README.md)

# Upgrading to 0.4

0.4.0 moves the package to Langfuse v4. It is a breaking release, and it is not
optional: **Langfuse Cloud removes every deprecated endpoint on 2026-11-16**,
including the ingestion endpoint this package used for traces, spans, generations
and events, and the read endpoints it used for observations, metrics, scores and
dataset runs.

```bash
composer require axyr/laravel-langfuse:^0.4
```

## What changed, in one paragraph

Traces now go to Langfuse's OpenTelemetry endpoint. OTLP is append-only, so an
observation can no longer be created and then updated: it is assembled in memory and
exported exactly once, when it ends. That single change is behind almost everything
else in this guide.

## Server requirements

| Surface | Requirement |
|---|---|
| Tracing (OTLP) | Langfuse Cloud (v3 and v4), self-hosted OSS **3.22.0+** |
| Scores, write and read | Langfuse Cloud (v3 and v4), self-hosted v3+ |
| Observations, metrics and experiments read APIs | **Langfuse v4** |

There is no fallback to the v1 read endpoints. If you self-host and read
observations, metrics or experiments, upgrade Langfuse to v4 first.

## 1. End your traces

This is the change that will actually bite. Nothing is sent while work is in
flight, and a trace that never ends is never exported.

```php
$trace = Langfuse::trace(new TraceBody(name: 'chat-request', input: $question));

// ... spans and generations ...

$trace->end(output: $answer);   // ← new
```

- Spans and generations already had `end()`; that call is now what sends them.
- The application terminate hook ends everything still open before the final flush,
  so HTTP requests keep working without code changes. Observations ended that way
  are marked `WARNING` with the status message `ended by shutdown`, which is a
  useful signal that a code path is missing an explicit `end()`.
- **In a queue worker, an Octane server or a long-running command, call
  `Langfuse::shutdown()`** at the end of the unit of work. It ends every open
  observation and flushes.
- `update()` after `end()` is ignored and logs a warning. A second `end()` likewise.

`update()` itself no longer sends anything; it folds fields into the in-memory trace
and they travel with it when it is exported. Fields the update leaves `null` keep
their current value now, and metadata is merged key by key - in 0.3 an update
replaced the body wholesale on the wire and relied on Langfuse merging it.

## 2. IDs are hex

OpenTelemetry identifies observations by hex IDs: 32 characters for a trace, 16 for
an observation. IDs you pass yourself are normalised into that shape once, and the
same input always produces the same ID.

```php
$trace = Langfuse::trace(new TraceBody(id: 'order-42'));

$trace->getId();  // 32 hex characters, NOT 'order-42'
```

**Use `getId()` for anything you link.** Scores and experiment items must reference
the returned value. `ScoreBody::$traceId` and `$observationId` are normalised the
same way, so a score written against `'order-42'` still lands on that trace - but
stored IDs from before the upgrade will not match anything that was ingested under
the old format.

New: `$trace->getRootObservationId()` returns the 16-hex ID of the trace's root span,
which is what experiment items and root-observation scores point at.

## 3. Trace-level attributes are on every span

In v4 the user, session, tags, metadata, release, version and environment live on
every observation row, and the read APIs filter on them. The package copies them
onto every span of the trace at flush time, so a value set on the trace after a
child ended is still picked up.

A `LANGFUSE_FLUSH_AT` flush in the middle of a request can still ship early spans
before a late update, so set what you filter on as early as you can - ideally when
the trace is created.

## 4. Removed and changed API

| Removed | Replacement |
|---|---|
| `Langfuse::getDatasetRun()`, `listDatasetRuns()`, `deleteDatasetRun()`, `createDatasetRunItem()`, `listDatasetRunItems()` | `listExperiments()`, `getExperiment()`, `listExperimentItems()`, and `TraceBody::forExperimentItem()` on the write side |
| `DatasetRunResponse`, `DatasetRunListResponse`, `DatasetRunWithItemsResponse`, `DatasetRunItemResponse`, `DatasetRunItemListResponse`, `CreateDatasetRunItemBody` | `ExperimentResponse`, `ExperimentListResponse`, `ExperimentItemResponse`, `ExperimentItemListResponse`, `ExperimentContext`, `ExperimentItemContext` |
| `ScoreTraceData`, `ScoreResponse::$traceId/$sessionId/$observationId/$datasetRunId/$trace/$stringValue` | `ScoreResponse::$subject` plus the `traceId()`, `observationId()`, `sessionId()` and `experimentId()` accessors; `value` is polymorphic |
| `Usage::$unit` | gone - v4 has no usage unit |
| `EventType::TraceCreate` and the other observation cases | only `EventType::ScoreCreate` remains |
| `LangfuseConfig::batchMetadata()`, `observationsUrl()`, `metricsUrl()`, `scoresV2Url()`, `datasetRunsUrl()`, `datasetRunItemsUrl()` | `Version::SDK_*`, `otelTracesUrl()`, `metricsV2Url()`, `scoresV3Url()`, `experimentsUrl()`, `experimentItemsUrl()` |
| `EventBatcherInterface::enqueue(IngestionEvent)` | `enqueue(CompletedObservation)` and `enqueueScore(ScoreBody, ?string $timestamp)` |

Changed signatures:

- `Langfuse::score(ScoreBody $body, ?string $timestamp = null)` - the second argument
  is the ingestion envelope timestamp, which is how a score is overwritten.
- `Langfuse::getObservation(string $id, ?string $fromStartTime = null, ?string $toStartTime = null, ?string $fields = null)` -
  v2 has no get-by-id route, so this is an `id` filter on the list and wants a time
  window. Without one it looks back 30 days.
- `ScoreQuery` was rewritten for v3: list filters are arrays, `page` is replaced by
  `cursor`, `datasetRunId` by `experimentId`, `scoreIds` by `id`, and `userId`,
  `operator`, `traceTags` and `filter` are gone.
- `ScoreListResponse::$meta` is a `CursorMeta` (`limit`, `cursor`), not a
  `PromptListMeta`.
- `MetricQuery` rejects any view other than `observations`, `scores-numeric`,
  `scores-boolean` and `scores-categorical`. **The `traces` view is gone**; use
  `observations` filtered or grouped on `isRootObservation`.
- Scores: no `stringValue` on the wire. Pass the string to `value`. The
  `stringValue` constructor argument still exists and is mapped into `value` for you.

New:

- `SpanBody::$type` takes an `ObservationType` (`Tool`, `Retriever`, `Agent`,
  `Chain`, `Evaluator`, `Embedding`, `Guardrail`, ...) so a span says what it did.
- `Usage::$details` and `$costDetails` carry arbitrary usage and cost keys - cached
  tokens, reasoning tokens - next to `input`, `output` and `total`.
- `ScoreBody::$datasetRunId`, `$metadata` and `$queueId`.
- `ScoreDataType::TEXT` and `ScoreDataType::CORRECTION`.
- Config: `LANGFUSE_SERVICE_NAME`, `LANGFUSE_COMPRESSION`, `LANGFUSE_RELEASE`.

## 5. `LANGFUSE_FLUSH_AT` counts differently

It now counts **completed observations and scores**, not raw events. A trace with
one generation used to be three events (trace-create, generation-create,
generation-update) and is now two observations. If you tuned the threshold, halve
it to keep the same batch size.

## 6. Test suites

`Langfuse::fake()` keeps every assertion name, but they mean what they say on the
new lifecycle:

- `assertTraceCreated()` and friends still assert the object was created.
- `assertTraceEnded()`, `assertSpanEnded()`, `assertGenerationEnded()` are new and
  assert it reached `end()`.
- **`assertEventCount()` counts what a flush would send**: exported observations plus
  queued scores. A trace with one generation is `2`, not `3`, and an observation that
  never ended is not counted. Expect to adjust these numbers.
- `$fake->events()` is gone. Use `$fake->observations()` (the `CompletedObservation`s
  a flush would send), `$fake->scores()`, `$fake->traces()`, `$fake->spans()`,
  `$fake->generations()`, or `$fake->exported()` for the OTLP request bodies.
- `assertDatasetRunItemCreated()` is replaced by `assertExperimentItemTraced()`.
- `withDatasetRun()` is replaced by `withExperiment()` and `withExperimentItem()`.

If you built on `RecordingEventBatcher` directly, it now records
`CompletedObservation[]` and `ScoreBody[]`; `events()` and `eventsOfType()` are
replaced by `observations()`, `observationsOfType(ObservationType)` and `scores()`.

## 7. Dataset runs became experiments

There is no "create experiment" call. An experiment is created implicitly by
tracing: attach an `ExperimentContext` to each item's trace.

```php
use Axyr\Langfuse\Dto\ExperimentContext;
use Axyr\Langfuse\Dto\ExperimentItemContext;

$experiment = ExperimentContext::named('run-2026-01', datasetId: 'ds-1');

$trace = Langfuse::trace((new TraceBody(name: 'qa-eval', input: $item->input))
    ->forExperimentItem($experiment, new ExperimentItemContext(
        itemId: $item->id,
        expectedOutput: $item->expectedOutput,
    )));

// ... run the pipeline ...

$trace->end(output: $answer);
```

Read them back with `listExperiments()` and `listExperimentItems()`; both need a
`fromStartTime` window. See [Querying](querying.md#experiments).

**There is no delete.** v4 has no endpoint that removes an experiment, which is why
`deleteDatasetRun()` is gone rather than reimplemented. The only way to remove the
data is `DELETE /api/public/traces` with the item trace IDs, done by hand - trace
deletion is rate limited to 50 requests per day on Hobby and 1,000 on Pro.

## 8. Verifying the upgrade

After deploying, check one real trace in the Langfuse UI:

1. The root observation, the generation and any child spans all appear, with the
   right nesting and start/end times.
2. The root observation shows the overall input and output.
3. User, session, tags, environment, version, release, trace name and metadata are
   on **every** observation, not just the root.
4. A score written with `traceId` plus a 16-hex `observationId` shows up under that
   observation.
5. No `ended by shutdown` status messages where you expected an explicit `end()`.

If traces show up 10-15 minutes late, something is stripping the
`x-langfuse-ingestion-version: 4` header in transit. See
[Troubleshooting](troubleshooting.md#traces-show-up-10-15-minutes-late).

---

Previous: [Troubleshooting](troubleshooting.md)
