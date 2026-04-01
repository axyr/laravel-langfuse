# Langfuse PHP SDK for Laravel

A Laravel package for integrating with [Langfuse](https://langfuse.com) - the open-source LLM observability platform. Track traces, spans, generations, events, and scores from your Laravel application with minimal setup.

## Problem

When building LLM-powered applications, you need visibility into what's happening: which prompts were sent, what the model returned, how long it took, how much it cost, and whether the output was any good. Without observability, debugging issues and improving quality is guesswork.

Langfuse solves this by providing a tracing and analytics platform purpose-built for LLM applications. This SDK gives your Laravel application a clean, idiomatic way to send that telemetry data to Langfuse.

## Features

- **Trace LLM interactions** - capture the full lifecycle of requests, from input to output
- **Nested observations** - organize work into traces, spans, and generations with automatic parent-child relationships
- **Generation tracking** - record model name, parameters, token usage, and costs for each LLM call
- **Scoring** - attach numeric, boolean, or categorical quality scores to traces and observations
- **Prompt management** - fetch, cache, and compile prompts from Langfuse with stale-while-revalidate caching and fallback support
- **Prism integration** - optional auto-instrumentation for [Prism](https://github.com/prism-php/prism) LLM calls
- **Per-request trace context** - optional middleware auto-creates a request trace; all Prism calls nest under it
- **Automatic batching** - events are queued and sent in batches to minimize HTTP overhead
- **Octane compatible** - scoped bindings reset state per request in long-running processes
- **Graceful degradation** - API failures are caught and logged, never thrown; a disabled mode silently no-ops
- **Auto-flush on shutdown** - queued events are flushed automatically when the application terminates
- **Facade support** - use `Langfuse::trace(...)` anywhere in your code
- **Testing utilities** - swap the client with a fake for assertions in your test suite

## Requirements

- PHP 8.4+
- Laravel 13

## Installation

Install via Composer:

```bash
composer require langfuse/langfuse-php
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=langfuse-config
```

Add your Langfuse credentials to `.env`:

```env
LANGFUSE_PUBLIC_KEY=pk-lf-...
LANGFUSE_SECRET_KEY=sk-lf-...
```

## Configuration

All configuration lives in `config/langfuse.php` and can be overridden via environment variables:

| Variable | Default | Description |
|---|---|---|
| `LANGFUSE_PUBLIC_KEY` | `""` | Your Langfuse project public key |
| `LANGFUSE_SECRET_KEY` | `""` | Your Langfuse project secret key |
| `LANGFUSE_BASE_URL` | `https://cloud.langfuse.com` | Langfuse API base URL (change for self-hosted) |
| `LANGFUSE_ENABLED` | `true` | Set to `false` to disable all tracing |
| `LANGFUSE_FLUSH_AT` | `10` | Number of events to batch before auto-flushing |
| `LANGFUSE_REQUEST_TIMEOUT` | `15` | HTTP request timeout in seconds |
| `LANGFUSE_PROMPT_CACHE_TTL` | `60` | Prompt cache TTL in seconds |
| `LANGFUSE_PRISM_ENABLED` | `false` | Enable automatic Prism LLM call tracing |

## Usage

### Basic trace

IDs and timestamps are auto-generated - just pass the fields you care about:

```php
use Langfuse\LangfuseFacade as Langfuse;
use Langfuse\Dto\TraceBody;

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

All fields except `name` are optional. The `id` and `timestamp` are auto-generated but can be overridden. Use `$trace->getId()` to retrieve the trace ID.

### Tracking an LLM generation

```php
use Langfuse\Dto\GenerationBody;
use Langfuse\Dto\Usage;

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

The `generation->end()` method also accepts `level` (an `ObservationLevel` enum) and `statusMessage` for error tracking. The `endTime` is auto-generated but can be overridden.

Use `$generation->getId()` and `$generation->getTraceId()` to retrieve the IDs.

### Usage and cost tracking

The `Usage` DTO supports both token counts and cost tracking:

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

### Spans for non-LLM work

Use spans to track any operation within a trace - database queries, API calls, processing steps:

```php
use Langfuse\Dto\SpanBody;

$span = $trace->span(new SpanBody(
    name: 'retrieve-context',
    environment: 'production',
));

// ... do work ...

$span->end(output: 'Retrieved 5 documents');
```

The `span->end()` method accepts `output`, `statusMessage`, and `endTime` (auto-generated by default).

Spans support nesting - use `$span->span()`, `$span->generation()`, and `$span->event()` to create child observations. Use `$span->getId()` and `$span->getTraceId()` to retrieve the IDs.

### Nesting observations

Spans and generations can be nested to represent complex workflows:

```php
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

### Events

Lightweight observations for logging discrete moments without a start/end lifecycle. The `id` and `startTime` are auto-generated:

```php
use Langfuse\Dto\EventBody;

$trace->event(new EventBody(
    name: 'cache-hit',
    input: ['key' => 'user-profile-42'],
    output: ['cached' => true],
    environment: 'production',
));
```

### Scores

Attach quality metrics to traces or specific observations. Supports `NUMERIC`, `BOOLEAN`, and `CATEGORICAL` data types:

```php
use Langfuse\Dto\ScoreBody;
use Langfuse\Enums\ScoreDataType;

// Score on a trace
$trace->score(new ScoreBody(
    name: 'user-satisfaction',
    value: 4.5,
    dataType: ScoreDataType::NUMERIC,
    comment: 'User rated the response positively',
));

// Score on a specific observation
$trace->score(new ScoreBody(
    name: 'relevance',
    observationId: $generation->getId(),
    stringValue: 'high',
    dataType: ScoreDataType::CATEGORICAL,
));

// Score without a trace (via client directly)
Langfuse::score(new ScoreBody(
    name: 'hallucination',
    traceId: $trace->getId(),
    value: 0.0,
    dataType: ScoreDataType::BOOLEAN,
    sessionId: 'session-abc',
    environment: 'production',
));
```

### Error tracking

Mark failed operations with a level and status message. Available levels are `DEBUG`, `DEFAULT`, `WARNING`, and `ERROR`:

```php
use Langfuse\Enums\ObservationLevel;

$generation->end(
    level: ObservationLevel::ERROR,
    statusMessage: 'Rate limited by provider',
);
```

### Flushing

Events are batched and auto-flushed when the queue reaches the `LANGFUSE_FLUSH_AT` threshold. They are also automatically flushed when the Laravel application terminates.

To flush manually:

```php
Langfuse::flush();
```

### Checking if tracing is enabled

```php
if (Langfuse::isEnabled()) {
    // tracing is active
}
```

### Request middleware

The optional `LangfuseMiddleware` auto-creates a trace for each HTTP request. All Prism LLM calls within that request automatically nest under it as generations:

```php
// In your route file or middleware group:
use Langfuse\Http\Middleware\LangfuseMiddleware;

Route::middleware(LangfuseMiddleware::class)->group(function () {
    Route::post('/chat', ChatController::class);
});
```

The middleware sets the trace name to the route name (or `METHOD /path` as fallback), captures the authenticated user ID, and includes request metadata.

You can also set a trace manually for the same nesting behavior:

```php
$trace = Langfuse::trace(new TraceBody(name: 'my-job'));
Langfuse::setCurrentTrace($trace);

// Subsequent Prism calls will nest under this trace
```

Use `Langfuse::currentTrace()` to retrieve the current request trace (returns `null` if none is set).

### Prompt management

Fetch prompts from your Langfuse project, with built-in caching and fallback support:

```php
use Langfuse\LangfuseFacade as Langfuse;

// Fetch and compile a text prompt
$prompt = Langfuse::prompt('movie-critic');
$compiled = $prompt->compile(['movie' => 'Dune 2']);

// Fetch a specific version or label
$prompt = Langfuse::prompt('movie-critic', version: 3);
$prompt = Langfuse::prompt('movie-critic', label: 'production');

// Provide a fallback for when the API is unavailable
$prompt = Langfuse::prompt('movie-critic', fallback: 'Review {{movie}} briefly.');

// Chat prompts work the same way
$prompt = Langfuse::prompt('chat-assistant', fallback: [
    ['role' => 'system', 'content' => 'You are a helpful assistant.'],
]);
$messages = $prompt->compile(['name' => 'World']);
```

Prompts are cached in-memory with a configurable TTL (`LANGFUSE_PROMPT_CACHE_TTL`). When the cache expires, the SDK revalidates from the API - if the API is unavailable, the stale cached version is returned.

Prompt objects expose metadata for inspection:

```php
$prompt->getName();          // 'movie-critic'
$prompt->getVersion();       // 3
$prompt->getConfig();        // ['temperature' => 0.7, ...]
$prompt->getLabels();        // ['production', 'latest']
$prompt->isFallback();       // true if using a fallback prompt
```

Link prompt metadata to generations for full traceability in the Langfuse UI:

```php
$prompt = Langfuse::prompt('movie-critic');
$generation = $trace->generation(new GenerationBody(
    name: 'review',
    model: 'gpt-4',
    metadata: $prompt->toLinkMetadata(),
));
```

### Prism integration

If you use [Prism](https://github.com/prism-php/prism) for LLM calls, enable automatic tracing with a single environment variable:

```env
LANGFUSE_PRISM_ENABLED=true
```

When enabled, the SDK wraps Prism's provider layer to automatically create traces and generations for every `text()`, `structured()`, and `stream()` call - including model parameters, token usage, and error tracking. No code changes required. Other Prism methods (`embeddings()`, `images()`, etc.) are passed through without tracing.

Multiple Prism calls within the same request share a single trace. If you use `LangfuseMiddleware`, Prism generations nest under the request trace automatically.

### Octane compatibility

The SDK is compatible with Laravel Octane and other long-running process servers. The `EventBatcher` and `LangfuseClient` use scoped bindings that reset per request, preventing event leakage between requests.

No additional configuration is needed - it works out of the box with Octane, RoadRunner, and FrankenPHP.

### Testing with fakes

The SDK provides a testing double that records events without making HTTP calls:

```php
use Langfuse\Dto\GenerationBody;
use Langfuse\Dto\TraceBody;
use Langfuse\LangfuseFacade as Langfuse;

// In your test
$fake = Langfuse::fake();

// Run your application code...
$trace = Langfuse::trace(new TraceBody(name: 'test'));
$trace->generation(new GenerationBody(name: 'chat'));

// Assert what was recorded
$fake->assertTraceCreated('test')
    ->assertGenerationCreated('chat')
    ->assertEventCount(2);

// Other available assertions
$fake->assertSpanCreated('name');
$fake->assertScoreCreated('name');
$fake->assertEventCreated('name');
$fake->assertNothingSent();

// Access raw recorded events for custom assertions
$events = $fake->events();
```

You can also pre-configure prompt responses for the fake:

```php
use Langfuse\Dto\TextPrompt;

$fake = Langfuse::fake();
$fake->withPrompt(new TextPrompt(name: 'test', version: 1, prompt: 'Hello {{name}}'));

$prompt = Langfuse::prompt('test');
$prompt->compile(['name' => 'World']); // "Hello World"
```

### Disabling in tests

Set `LANGFUSE_ENABLED=false` in your `.env.testing` to silently disable all tracing. The SDK substitutes a no-op batcher - no events are queued or sent, and no code changes are needed.

## Architecture

```
┌──────────────────────────────────────────────────┐
│  Your Application                                │
│                                                  │
│  Langfuse::trace()  →  LangfuseTrace             │
│                         ├── span()  →  LangfuseSpan
│                         ├── generation() → LangfuseGeneration
│                         ├── event()                │
│                         └── score()                │
└───────────────┬──────────────────────────────────┘
                │ enqueue(IngestionEvent)
                ▼
        ┌───────────────┐
        │  EventBatcher │  queues events, auto-flushes at threshold
        └───────┬───────┘
                │ send(IngestionBatch)
                ▼
     ┌─────────────────────┐
     │  IngestionApiClient │  HTTP POST to Langfuse API
     └─────────────────────┘
```

All DTOs are immutable readonly classes with auto-generated IDs and timestamps. API and batching failures are caught and logged - they never propagate exceptions to your application.

## Testing

```bash
composer test
```

## License

MIT
