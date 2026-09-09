# Changelog

All notable changes to `axyr/laravel-langfuse` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
