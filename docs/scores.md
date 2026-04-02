[Back to documentation](README.md)

# Scores

Attach quality metrics to traces or observations. Three data types: `NUMERIC`, `BOOLEAN`, `CATEGORICAL`.

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

```php
$trace->score(new ScoreBody(
    name: 'relevance',
    observationId: $generation->getId(),
    stringValue: 'high',
    dataType: ScoreDataType::CATEGORICAL,
));
```

## Scoring via the facade

Score without a trace object by providing the trace ID directly:

```php
Langfuse::score(new ScoreBody(
    name: 'hallucination',
    traceId: $trace->getId(),
    value: 0.0,
    dataType: ScoreDataType::BOOLEAN,
    sessionId: 'session-abc',
    environment: 'production',
));
```

## Deleting a score

```php
Langfuse::deleteScore('score-id');
```

---

Previous: [Spans and Events](spans-and-events.md) | Next: [Prompt Management](prompt-management.md)
