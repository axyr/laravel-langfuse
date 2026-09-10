[Back to documentation](README.md)

# Testing

## Using fakes

The SDK provides a fake that records observations without making HTTP calls:

```php
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\LangfuseFacade as Langfuse;

$fake = Langfuse::fake();

// Run your application code...
$trace = Langfuse::trace(new TraceBody(name: 'test'));
$generation = $trace->generation(new GenerationBody(name: 'chat'));
$generation->end(output: 'answer');
$trace->end();

// Assert what was recorded
$fake->assertTraceCreated('test')
    ->assertGenerationEnded('chat')
    ->assertEventCount(2);
```

### Created vs ended

The v4 lifecycle separates the two, and so do the assertions:

| Assertion | Meaning |
|---|---|
| `assertTraceCreated(?string $name)` | a trace was created, ended or not |
| `assertSpanCreated(?string $name)` | a span object was created |
| `assertGenerationCreated(?string $name)` | a generation object was created |
| `assertTraceEnded(?string $name)` | the trace reached `end()` |
| `assertSpanEnded(?string $name)` | the span reached `end()` |
| `assertGenerationEnded(?string $name)` | the generation reached `end()` |
| `assertEventCreated(?string $name)` | an event was recorded (events export immediately) |
| `assertScoreCreated(?string $name)` | a score was enqueued |
| `assertScoreDeleted(?string $scoreId)` | a score was deleted |
| `assertPromptCreated(?string $name)` | a prompt was created |
| `assertNothingSent()` | nothing was created and nothing recorded |
| `assertEventCount(int)` | counts what a flush would send |

> **`assertEventCount()` changed in 0.4.0.** It counts exported observations plus
> queued scores, so a trace with one generation is `2`, not the `3` create/update
> events v3 sent. An observation that never ended is not counted at all.

### Inspecting what was recorded

```php
use Axyr\Langfuse\Tracing\CompletedObservation;

// The objects that were created, ended or not
$fake->traces();
$fake->spans();
$fake->generations();

// What a flush would send
$fake->observations();  // CompletedObservation[]
$fake->scores();        // ScoreBody[]

// Assert on the contents of one exported observation
$fake->assertObservationHas('chat', function (CompletedObservation $observation): bool {
    return $observation->body->model === 'gpt-4'
        && $observation->context->body()->userId === 'user-1';
});

// The OTLP request bodies a flush would post, for payload-level tests
$fake->exported();
```

### Testing the shutdown path

`Langfuse::shutdown()` on the fake behaves like the real client: it ends every
observation that is still open, then flushes.

```php
$trace = Langfuse::trace(new TraceBody(name: 'job'));
$trace->span(new SpanBody(name: 'work'));

Langfuse::shutdown();

$fake->assertTraceEnded('job')->assertSpanEnded('work');
```

## Pre-configured prompts

Set up prompt responses for the fake:

```php
use Axyr\Langfuse\Dto\TextPrompt;

$fake = Langfuse::fake();
$fake->withPrompt(new TextPrompt(name: 'test', version: 1, prompt: 'Hello {{name}}'));

$prompt = Langfuse::prompt('test');
$prompt->compile(['name' => 'World']); // "Hello World"
```

## Faking read calls

The fake also serves the read/query API ([Querying](querying.md)) without hitting
the network. Seed it with the responses your code should receive, then assert on the
reads and creates it performed.

```php
use Axyr\Langfuse\Dto\DatasetItemResponse;
use Axyr\Langfuse\Dto\DatasetResponse;
use Axyr\Langfuse\Dto\ExperimentItemResponse;
use Axyr\Langfuse\Dto\ExperimentResponse;
use Axyr\Langfuse\Dto\ObservationResponse;
use Axyr\Langfuse\Dto\ScoreResponse;

$fake = Langfuse::fake();

// Seed read responses
$fake->withScore(ScoreResponse::fromArray([
    'id' => 'score-abc', 'name' => 'accuracy', 'dataType' => 'NUMERIC', 'value' => 0.95,
]));
$fake->withObservation(ObservationResponse::fromArray(['id' => '0192f1b42c7e7a1b', 'type' => 'GENERATION']));
$fake->withMetrics([['traceName' => 'chat', 'count_count' => 42]]); // rows returned by queryMetrics()
$fake->withDataset(DatasetResponse::fromArray(['id' => 'ds-1', 'name' => 'qa-eval']));
$fake->withDatasetItem(DatasetItemResponse::fromArray(['id' => 'di-1', 'datasetName' => 'qa-eval']));
$fake->withExperiment(ExperimentResponse::fromArray([
    'id' => 'nightly-eval', 'name' => 'nightly-eval', 'datasetId' => 'ds-1',
]));
$fake->withExperimentItem(ExperimentItemResponse::fromArray([
    'id' => 'aaaaaaaaaaaaaaaa', 'traceId' => '0192f1b42c7e7a1b8d3e9f0a1b2c3d4e', 'experimentItemId' => 'di-1',
]));

// Reads now return the seeded data
expect(Langfuse::getScore('score-abc')?->value)->toBe(0.95);

// Assert on creates performed against the fake
$fake->assertDatasetCreated('qa-eval')
    ->assertDatasetItemCreated('qa-eval')
    ->assertExperimentItemTraced('nightly-eval');
```

Seeders: `withScore()`, `withObservation()`, `withMetrics(array $rows)`,
`withDataset()`, `withDatasetItem()`, `withExperiment()`, `withExperimentItem()`.
Read-side assertions: `assertDatasetCreated(?string $name)`,
`assertDatasetItemCreated(?string $datasetName)`,
`assertExperimentItemTraced(?string $experimentName)` - which reads the experiment
context off the traces that were created, because writing an experiment item is
tracing in v4.
Each assertion (and `with*` seeder) returns `$this` for chaining. Unseeded reads
return `null`, matching the resilient behaviour of the real clients.

## Disabling tracing in tests

Set `LANGFUSE_ENABLED=false` in `.env.testing`. The SDK swaps in a no-op batcher - observations are still built but nothing is queued and nothing is sent, no code changes needed.

---

Previous: [Batching and Flushing](batching-and-flushing.md) | Next: [Architecture](architecture.md)
