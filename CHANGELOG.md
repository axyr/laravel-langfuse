# Changelog

All notable changes to `axyr/laravel-langfuse` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Environments** — new `environment` config (`LANGFUSE_TRACING_ENVIRONMENT`, matching the
  official Langfuse SDKs) stamped on every ingestion event body that does not set one.
  Langfuse environments are immutable after first ingestion, so they must be present at
  event creation; this applies to both the sync and queued batchers via `IngestionBatch`.
- **Prompt-to-generation linking** — prompts resolved through `PromptManager::get()` are
  registered in a request-scoped `CurrentPromptRegistry`; the Laravel AI subscriber consumes
  the registered prompt when recording a generation and sets `promptName`/`promptVersion`,
  enabling per-prompt-version metrics in Langfuse. Fallback prompts are never linked, and
  each resolved prompt links to exactly one generation.

### Changed

- `PromptManager` is now bound as `scoped` (was `singleton`) so it always sees the current
  scope's `CurrentPromptRegistry` under Octane and queue workers.

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
