[Back to documentation](README.md)

# Tracing

Traces are the top-level container. Each trace represents one unit of work - an API request, a background job, a pipeline run.

## Creating a trace

IDs and timestamps are auto-generated. Just pass the fields you care about:

```php
use Axyr\Langfuse\LangfuseFacade as Langfuse;
use Axyr\Langfuse\Dto\TraceBody;

$trace = Langfuse::trace(new TraceBody(
    name: 'chat-request',
    userId: 'user-42',
    sessionId: 'session-abc',
    metadata: ['key' => 'value'],
    tags: ['chat', 'gpt-4'],
    environment: 'production',
    release: 'v1.2.0',
    version: '1',
    public: false,
));
```

All fields except `name` are optional. Use `$trace->getId()` to get the trace ID.

## IDs

Langfuse v4 speaks OpenTelemetry, which identifies observations by hex IDs:

- a **trace ID** is 32 lowercase hex characters,
- an **observation ID** (span, generation, event, and the trace's root observation) is 16.

The package generates those for you. If you pass your own `id`, it is normalised into the right shape once, when the body is constructed: a value that is already the right length is kept, a UUID trace ID loses its dashes, and anything else is hashed deterministically. The same input always produces the same ID, so passing `'order-42'` twice links to the same trace.

That means `getId()` can return something different from what you passed in. **Always use the returned value** when you write scores or dataset items:

```php
$trace = Langfuse::trace(new TraceBody(id: 'order-42'));

$trace->getId();                  // 32 hex characters, not 'order-42'
$trace->getRootObservationId();   // 16 hex characters: the trace's root span
```

Timestamps stay ISO 8601 strings on the PHP side; the nanosecond format OpenTelemetry wants is an implementation detail of the wire payload.

> Auto-generated IDs and timestamps apply to all observation types - traces, spans, generations, events. Mentioned once here, applies everywhere.

## Ending a trace

v4 is append-only: nothing is sent when an observation is created, and an observation cannot be edited after it arrives. Each one is assembled in memory and exported exactly once, when it ends.

```php
$trace = Langfuse::trace(new TraceBody(name: 'chat-request', input: $question));

// ... do work, create spans and generations ...

$trace->end(output: $answer);
```

- `end()` exports the trace's root observation. A second `end()` is ignored and logs a warning.
- Anything still open when the application terminates is ended for you by the shutdown hook, so middleware and the integrations keep working without extra code. Children ended that way are marked `WARNING` with the status message `ended by shutdown`.
- In a long-running process - a queue worker, an Octane server, a CLI command - call `Langfuse::shutdown()` yourself at the end of the unit of work. It ends every open observation and flushes.
- A crash between creating and ending an observation loses that observation.

## Updating a trace

`update()` folds fields into the in-memory trace. Nothing is sent by the call itself; the values travel with the trace when it is exported. Fields the update leaves `null` keep their current value, and metadata is merged key by key:

```php
$trace->update(new TraceBody(
    output: 'The final response text',
    metadata: ['tokens' => 150, 'cached' => false],
    tags: ['completed'],
));
```

Updates after `end()` are ignored and log a warning.

## Trace-level attributes

In v4, trace-level values live on every observation row, and the observations and metrics APIs filter on them. The package copies them onto every span of the trace when the batch is serialised:

`name`, `userId`, `sessionId`, `tags`, `metadata`, `release`, `version`, `environment`, `public`.

Because the copy happens at flush time, anything set on the trace before the batch leaves the process is picked up - including values set after a child already ended. A flush triggered by `LANGFUSE_FLUSH_AT` in the middle of a request can still ship early spans before a late update, so set the values you filter on as early as you can, ideally when the trace is created.

Per-observation `version` and `environment` win over the trace value for that span. Only top-level metadata keys are filterable in Langfuse; nested values are stored as JSON strings.

## Nesting observations

Spans and generations nest to represent complex workflows:

```php
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Dto\Usage;

$trace = Langfuse::trace(new TraceBody(name: 'rag-pipeline'));

$retrievalSpan = $trace->span(new SpanBody(name: 'retrieval'));

    $embeddingGen = $retrievalSpan->generation(new GenerationBody(
        name: 'embed-query',
        model: 'text-embedding-3-small',
    ));
    $embeddingGen->end(output: [0.1, 0.2, 0.3], usage: new Usage(input: 8, total: 8));

    $searchSpan = $retrievalSpan->span(new SpanBody(name: 'vector-search'));
    $searchSpan->end(output: '5 results found');

$retrievalSpan->end(output: 'context ready');

$completionGen = $trace->generation(new GenerationBody(
    name: 'answer',
    model: 'gpt-4',
    input: [['role' => 'user', 'content' => 'What is RAG?']],
));
$completionGen->end(
    output: [['role' => 'assistant', 'content' => 'RAG stands for...']],
    usage: new Usage(input: 120, output: 200, total: 320),
);

$trace->end(output: 'RAG stands for...');
```

Call `span()`, `generation()`, or `event()` on both traces and spans. Parent-child relationships are set automatically: observations created from the trace nest under its root observation, observations created from a span nest under that span.

---

Previous: [Configuration](configuration.md) | Next: [Generations](generations.md)
