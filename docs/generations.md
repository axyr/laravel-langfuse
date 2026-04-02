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

The `end()` method also accepts `level` (an `ObservationLevel` enum) and `statusMessage` for error tracking. The `endTime` is auto-generated but can be overridden.

Use `$generation->getId()` and `$generation->getTraceId()` to get the IDs.

## Usage and cost tracking

The `Usage` DTO supports both token counts and cost:

```php
$generation->end(
    output: 'Response text',
    usage: new Usage(
        input: 100,
        output: 200,
        total: 300,
        unit: 'TOKENS',
        inputCost: 0.0005,
        outputCost: 0.0015,
        totalCost: 0.002,
    ),
);
```

## Error tracking

Mark failed operations with a level and status message. Available levels: `DEBUG`, `DEFAULT`, `WARNING`, `ERROR`.

```php
use Axyr\Langfuse\Enums\ObservationLevel;

$generation->end(
    level: ObservationLevel::ERROR,
    statusMessage: 'Rate limited by provider',
);
```

---

Previous: [Tracing](tracing.md) | Next: [Spans and Events](spans-and-events.md)
