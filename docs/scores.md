[Back to documentation](README.md)

# Scores

Attach quality metrics to traces, observations, sessions or experiments. Data types: `NUMERIC`, `BOOLEAN`, `CATEGORICAL`, `TEXT` and `CORRECTION`.

Scores are the one thing that still travels over `POST /api/public/ingestion`; v4 keeps that endpoint for `score-create` events. Everything else moved to the OTLP endpoint.

## Scoring a trace

```php
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Enums\ScoreDataType;

$trace->score(new ScoreBody(
    name: 'user-satisfaction',
    value: 4.5,
    dataType: ScoreDataType::NUMERIC,
    comment: 'User rated the response positively',
));
```

## Scoring a specific observation

`observationId` is the 16 hex character ID of the observation, which is exactly what `getId()` returns:

```php
$trace->score(new ScoreBody(
    name: 'relevance',
    observationId: $generation->getId(),
    value: 'high',
    dataType: ScoreDataType::CATEGORICAL,
));
```

A `traceId` or `observationId` you pass yourself is normalised the same way observation IDs are, so a score written against `'order-42'` still lands on the trace created from `'order-42'`. Use `getId()` where you can.

## Score values

`value` is polymorphic in v4:

| Data type | Value |
|---|---|
| `NUMERIC` | a number |
| `BOOLEAN` | a bool, sent as `1` / `0` |
| `CATEGORICAL`, `TEXT`, `CORRECTION` | a string (1-500 characters) |

There is no `stringValue` on the wire any more. The `stringValue` constructor argument still exists for backwards compatibility and is mapped into `value` for you, but prefer passing the string straight to `value`.

## Overwriting a score

Unlike traces and observations, a score can be overwritten. Langfuse identifies it by `id`, `name` and `timestamp` at date granularity. The timestamp is not a body field: it is the ingestion envelope timestamp, so pass it as the second argument:

```php
Langfuse::score(
    new ScoreBody(id: 'daily-quality', name: 'quality', value: 0.9),
    timestamp: '2026-09-10T00:00:00.000000Z',
);
```

`$trace->score()` takes the same second argument.

## Scoring via the facade

Score without a trace object by providing the trace ID directly:

```php
Langfuse::score(new ScoreBody(
    name: 'hallucination',
    traceId: $trace->getId(),
    value: false,
    dataType: ScoreDataType::BOOLEAN,
    sessionId: 'session-abc',
    environment: 'production',
));
```

## Experiment scores

Point a score at an experiment with `datasetRunId` - the field kept its v2 name, but in v4 it holds the experiment ID:

```php
Langfuse::score(new ScoreBody(
    name: 'accuracy',
    datasetRunId: 'nightly-eval',
    value: 0.87,
    dataType: ScoreDataType::NUMERIC,
));
```

To score one item of an experiment instead, use the item trace's `traceId` and its root observation ID. See [Experiments](querying.md#experiments).

`ScoreBody` also carries `metadata` and `queueId` for annotation workflows.

## Deleting a score

```php
Langfuse::deleteScore('score-id');
```

## Reading scores back

This page covers *writing* scores. To fetch scores by id, list them with filters,
or aggregate them, see [Querying (read API)](querying.md#scores).

---

Previous: [Spans and Events](spans-and-events.md) | Next: [Prompt Management](prompt-management.md)
