# Changelog

All notable changes to `axyr/laravel-langfuse` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.4.0] - 2026-09-10

Langfuse v4. Tracing moves to the OpenTelemetry endpoint and the read APIs move to
their current versions. **Langfuse Cloud removes every deprecated endpoint on
2026-11-16**, so this release is required to keep sending data after that date.
See [docs/upgrade-to-0.4.md](docs/upgrade-to-0.4.md) for the migration guide.

Compatibility: Langfuse Cloud (v3 and v4) and self-hosted 3.22.0+ for tracing and
scores; **Langfuse v4** for the observations, metrics and experiments read APIs.

### Changed

- **OTLP ingestion** - traces, spans, generations and events are posted to
  `POST /api/public/otel/v1/traces` as OTLP/JSON with the
  `x-langfuse-ingestion-version: 4` header. Scores stay on
  `POST /api/public/ingestion` as `score-create` events, which v4 keeps supporting.
- **Export once** - v4 is append-only, so an observation is assembled in memory and
  exported exactly once, when it ends. Creating a trace, span or generation sends
  nothing; `end()` does. `update()` folds fields into the in-memory trace instead of
  emitting a second event, keeping fields the update leaves `null` and merging
  metadata key by key. A second `end()`, or an `update()` after `end()`, is ignored
  and logs a warning.
- **`end()` and `shutdown()`** - `LangfuseTrace::end(?string $endTime, mixed $output)`
  exports the trace's root observation. `Langfuse::shutdown()` ends every observation
  that is still open and flushes; the application terminate hook calls it, so HTTP
  requests keep working unchanged. Observations ended by the shutdown hook are marked
  `WARNING` with the status message `ended by shutdown`. Long-running processes -
  queue workers, Octane, CLI commands - should call `shutdown()` themselves.
- **Hex IDs** - trace IDs are 32 lowercase hex characters and observation IDs 16, as
  OpenTelemetry requires. A custom `id` is normalised into that shape once, so the
  same input always yields the same ID and existing call sites keep working;
  `getId()` can therefore differ from what was passed in. New
  `LangfuseTrace::getRootObservationId()`.
- **Trace-level attributes on every span** - name, user, session, tags, metadata,
  release, version, environment and `public` are copied onto every observation of the
  trace when the batch is serialised, which is what the v4 observations and metrics
  filters read. Values set on the trace after a child ended are still picked up.
- **Hierarchy** - observations created from a trace now nest under the trace's root
  observation instead of becoming additional roots.
- **`flush_at` semantics** - `LANGFUSE_FLUSH_AT` counts completed observations and
  scores, not raw events. A trace with one generation is 2 items, not 3.
- **Observation reads** - `getObservation()` uses `GET /api/public/v2/observations`
  with an `id` filter and gained `$fromStartTime`, `$toStartTime` and `$fields`
  arguments; without a window it looks back 30 days. `ObservationResponse` gained the
  v2 field groups (`isRootObservation`, `userId`, `sessionId`, `traceName`, `tags`,
  `release`, `totalCost`, `timeToFirstToken`, `promptId`, and more).
- **Metrics** - `queryMetrics()` targets `GET /api/public/v2/metrics`. The `traces`
  view is gone; use `observations` filtered or grouped on `isRootObservation`.
  `MetricQuery` validates the view at construction.
- **Score reads** - `getScore()` and `getScores()` use `GET /api/public/v3/scores`:
  cursor pagination, comma-separated list filters, polymorphic `value`, and a
  `subject` describing what the score is attached to.
- **Score writes** - `value` accepts `float|bool|string`; string scores go into
  `value` because v4 has no `stringValue` on the wire (the `stringValue` argument is
  kept and mapped for you). `Langfuse::score()` and `LangfuseTrace::score()` take a
  second `?string $timestamp` argument, the ingestion envelope timestamp that decides
  whether a score overwrites an earlier one.
- **Queue jobs** - `SendIngestionBatchJob` now carries scores only and gained a retry
  policy; the new `SendOtelBatchJob` carries observations.
- **Fake assertions** - `assertEventCount()` counts what a flush would send
  (exported observations plus queued scores), so there is no create/update double
  counting and an observation that never ended is not counted.

### Added

- `Axyr\Langfuse\Version` with `SDK_NAME`, `SDK_LANGUAGE` and `SDK_VERSION`,
  reported as the OTLP resource attributes, the instrumentation scope and the score
  batch metadata.
- **Experiments** - `listExperiments()`, `getExperiment()` and
  `listExperimentItems()` on `GET /api/public/experiments` and
  `/api/public/experiment-items`, with `ExperimentQuery`, `ExperimentItemQuery`,
  `ExperimentResponse`, `ExperimentListResponse`, `ExperimentItemResponse` and
  `ExperimentItemListResponse`. On the write side, `ExperimentContext`,
  `ExperimentItemContext` and `TraceBody::forExperimentItem()` turn a trace into an
  experiment item.
- **Observation types** - `ObservationType` enum (`agent`, `tool`, `chain`,
  `retriever`, `evaluator`, `embedding`, `guardrail` next to span, generation and
  event) and a `type` override on `SpanBody`. The Laravel AI and Neuron AI
  integrations mark their tool spans as `tool`, and Neuron AI's RAG spans as
  `retriever`.
- **Usage details** - `Usage::$details` and `Usage::$costDetails` carry arbitrary
  usage and cost keys (cached tokens, reasoning tokens) next to `input`, `output`
  and `total`.
- `ScoreBody::$datasetRunId` (the experiment ID), `$metadata` and `$queueId`;
  `ScoreDataType::TEXT` and `ScoreDataType::CORRECTION`.
- `ObservationQuery::$sessionId`, `$isRootObservation` and `ObservationQuery::idFilter()`.
- New config keys: `LANGFUSE_SERVICE_NAME` (the OpenTelemetry `service.name`),
  `LANGFUSE_COMPRESSION` (gzip the OTLP body, off by default) and `LANGFUSE_RELEASE`
  (default release for traces that do not set one).
- New fake assertions and accessors: `assertTraceEnded()`, `assertSpanEnded()`,
  `assertGenerationEnded()`, `assertObservationHas()`,
  `assertExperimentItemTraced()`, `traces()`, `spans()`, `generations()`,
  `observations()`, `scores()`, `exported()`, `withExperiment()`,
  `withExperimentItem()`. `Langfuse::fake()` now applies environment and trace
  context stamping the same way the real client does.
- Batches are split so each OTLP request stays below 4 MB, under the 5 MB server
  limit.

### Removed

- Legacy ingestion for observations. `EventType` keeps only `ScoreCreate`;
  `IngestionEvent`, `IngestionBatch` and `IngestionResponse` now serve scores only.
- `Langfuse::getDatasetRun()`, `listDatasetRuns()`, `deleteDatasetRun()`,
  `createDatasetRunItem()` and `listDatasetRunItems()`, together with
  `DatasetRunApiClient`, `DatasetRunResponse`, `DatasetRunListResponse`,
  `DatasetRunWithItemsResponse`, `DatasetRunItemResponse`,
  `DatasetRunItemListResponse` and `CreateDatasetRunItemBody`. v4 has no endpoint
  that deletes an experiment; the manual path is `DELETE /api/public/traces` with the
  item trace IDs, which is rate limited to 50 requests per day on Hobby.
- `ScoreTraceData` and the `ScoreResponse` fields it fed (`traceId`, `sessionId`,
  `observationId`, `datasetRunId`, `trace`, `stringValue`), replaced by `subject`
  and the `traceId()`, `observationId()`, `sessionId()` and `experimentId()`
  accessors.
- `Usage::$unit` - v4 has no usage unit.
- `LangfuseConfig::batchMetadata()`, `observationsUrl()`, `metricsUrl()`,
  `scoresV2Url()`, `datasetRunsUrl()` and `datasetRunItemsUrl()`.
- `RecordingEventBatcher::events()` and `eventsOfType()`, replaced by
  `observations()`, `observationsOfType()` and `scores()`.

## [0.3.0] - 2026-09-09

### Added

- **Environments** - new `environment` config (`LANGFUSE_TRACING_ENVIRONMENT`, matching the
  official Langfuse SDKs). Traces and scores created through the client are stamped with it
  unless they set their own, and a trace passes its environment down to every span,
  generation, event and score created from it. Langfuse environments are immutable after
  first ingestion, so they are applied when the event is created. Malformed values are
  rejected at config load with an `InvalidArgumentException` instead of silently failing
  ingestion.
- **Prompt-to-generation linking** - prompts resolved through `Langfuse::prompt()` are
  registered in a request-scoped `CurrentPromptRegistry`. The Laravel AI, Prism and Neuron AI
  integrations consume the registered prompt when they record their next generation and set
  `promptName`/`promptVersion`, enabling per-prompt-version metrics in Langfuse. Fallback
  prompts are never linked, and each resolved prompt links to at most one generation.
  `GenerationBody::withPrompt()` is available for manual tracing, and `Langfuse::fake()`
  registers prompts too so linking can be asserted in tests.
- **User and session tracing** - traces created through the client get a default `userId`
  and `sessionId` from a `TraceContextResolverInterface` when they do not set their own.
  The default resolver reads the already authenticated user without triggering
  authentication. The Laravel AI integration tags agent traces with the conversation id as
  `sessionId` and the conversation participant as `userId`, including the first turn of a
  new conversation and traces adopted from `LangfuseMiddleware` or manual tracing. Two new
  switches, `LANGFUSE_USER_TRACING` and `LANGFUSE_SESSION_TRACING` (both default `true`),
  turn either off. Resolver failures are logged and never interrupt the traced code.

### Fixed

- **Laravel AI traces had no output** - the response text is now set as the trace output,
  but only on traces the integration created itself, so request traces, manual workflow
  traces and traces shared by nested agents keep their own output.
- `LangfuseTrace::update()` keeps the original trace timestamp instead of re-sending the
  current time.

## [0.2.0] - 2026-06-28

### Added

- **Read / query API** for fetching evaluation data back out of Langfuse via the
  public REST API. New facade/client methods:
  - **Scores** — `getScore()`, `getScores(ScoreQuery)` (v2 list + get-by-id; `deleteScore()` unchanged on v1).
  - **Observations** — `getObservation()`, `getObservations(ObservationQuery)` (cursor pagination, field-group selection via the v2 endpoint).
  - **Metrics / Query API** — `queryMetrics(MetricQuery)` for `traces`, `observations`, `scores-numeric`, and `scores-categorical` views (the trace-level analytics path; there is no traces-list endpoint).
  - **Datasets** — `getDataset()`, `listDatasets()`, `createDataset()`.
  - **Dataset items** — `getDatasetItem()`, `listDatasetItems()`, `createDatasetItem()`, `deleteDatasetItem()`.
  - **Dataset runs & run items** — `getDatasetRun()`, `listDatasetRuns()`, `deleteDatasetRun()`, `createDatasetRunItem()`, `listDatasetRunItems()`.
- New typed DTOs for every read surface (queries, responses, list responses with
  pagination meta) and a `DatasetStatus` enum.
- `LangfuseFake` read support: seeders (`withScore()`, `withObservation()`,
  `withMetrics()`, `withDataset()`, `withDatasetItem()`, `withDatasetRun()`) and
  creation assertions (`assertDatasetCreated()`, `assertDatasetItemCreated()`,
  `assertDatasetRunItemCreated()`).
- Documentation: [`docs/querying.md`](docs/querying.md) (read API + evaluation
  workflow), expanded testing docs, and a cross-link from the scores docs.

### Notes

- All read methods are **resilient**: on a network error or non-2xx response they
  log a warning and return `null` (a successful-but-empty list returns a list DTO
  with an empty `data` array — distinct from `null`).
- Array query filters (e.g. `environment`, `traceTags`) are serialised as repeated
  bare keys (`environment=prod&environment=staging`) to match the Langfuse API.
- Reads go through the public REST API only; the package never queries the
  Langfuse database directly.

### Changed

- Bumped the reported Langfuse SDK-protocol version to `2.1.0` (the value sent in
  ingestion metadata for v3 compatibility; unrelated to this package's release version).

## [0.1.0]

- Baseline: ingestion (traces, spans, generations, events, scores), prompt
  management, automatic batching/flushing, and auto-instrumentation for Prism,
  Laravel AI, and Neuron AI. See the [documentation](docs/README.md).
