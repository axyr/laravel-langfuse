[Back to documentation](README.md)

# Generations

Generations represent LLM calls. This is the core observation type for tracking model interactions.

## Tracking a generation

```php
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\Usage;

$generation = $trace->generation(new GenerationBody(
    name: 'chat-completion',
    model: 'gpt-4',
    input: [['role' => 'user', 'content' => 'Explain observability']],
    modelParameters: ['temperature' => 0.7, 'max_tokens' => 500],
    promptName: 'explain-topic',
    promptVersion: 2,
    environment: 'production',
));

// After the LLM responds:
$generation->end(
    output: [['role' => 'assistant', 'content' => 'Observability is...']],
    usage: new Usage(input: 12, output: 85, total: 97),
);
```

**`end()` is what sends the generation.** Creating one records the start time and nothing else; the finished observation is exported exactly once, when it ends. A generation that never ends does not appear in Langfuse, unless the application shutdown hook ends it. Ending twice is ignored and logs a warning.

`end()` also accepts `level` (an `ObservationLevel` enum) and `statusMessage` for error tracking. The `endTime` is auto-generated but can be overridden.

Use `$generation->getId()` and `$generation->getTraceId()` to get the IDs.

## Usage and cost tracking

The `Usage` DTO carries token counts and cost:

```php
$generation->end(
    output: 'Response text',
    usage: new Usage(
        input: 100,
        output: 200,
        total: 300,
        inputCost: 0.0005,
        outputCost: 0.0015,
        totalCost: 0.002,
    ),
);
```

`input`, `output` and `total` are the conventional keys Langfuse understands out of the box. v4 accepts arbitrary extra usage and cost keys, so pass anything else your provider reports - cached tokens, reasoning tokens - through `details` and `costDetails`:

```php
$generation->end(
    output: 'Response text',
    usage: new Usage(
        input: 100,
        output: 200,
        total: 300,
        totalCost: 0.002,
        details: ['cache_read' => 40, 'reasoning' => 15],
        costDetails: ['cache_read' => 0.0001],
    ),
);
```

> `Usage::$unit` is gone. v4 has no usage unit; every count is a token count keyed by name.

## Error tracking

Mark failed operations with a level and status message. Available levels: `DEBUG`, `DEFAULT`, `WARNING`, `ERROR`.

```php
use Axyr\Langfuse\Enums\ObservationLevel;

$generation->end(
    level: ObservationLevel::ERROR,
    statusMessage: 'Rate limited by provider',
);
```

An `ERROR` level also sets the OpenTelemetry span status to error, with the status message attached.

---

Previous: [Tracing](tracing.md) | Next: [Spans and Events](spans-and-events.md)
