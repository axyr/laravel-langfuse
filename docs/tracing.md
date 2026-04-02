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

> Auto-generated IDs and timestamps apply to all observation types - traces, spans, generations, events. Mentioned once here, applies everywhere.

## Updating a trace

Add output or metadata after the response is generated. The trace ID is preserved - Langfuse merges the update:

```php
$trace->update(new TraceBody(
    output: 'The final response text',
    metadata: ['tokens' => 150, 'cached' => false],
    tags: ['completed'],
));
```

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
```

Call `span()`, `generation()`, or `event()` on both traces and spans. Parent-child relationships are set automatically.

---

Previous: [Configuration](configuration.md) | Next: [Generations](generations.md)
