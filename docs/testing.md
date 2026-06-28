[Back to documentation](README.md)

# Testing

## Using fakes

The SDK provides a fake that records events without making HTTP calls:

```php
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\LangfuseFacade as Langfuse;

$fake = Langfuse::fake();

// Run your application code...
$trace = Langfuse::trace(new TraceBody(name: 'test'));
$trace->generation(new GenerationBody(name: 'chat'));

// Assert what was recorded
$fake->assertTraceCreated('test')
    ->assertGenerationCreated('chat')
    ->assertEventCount(2);

// All available assertions
$fake->assertSpanCreated('name');
$fake->assertScoreCreated('name');
$fake->assertEventCreated('name');
$fake->assertScoreDeleted('score-id');
$fake->assertPromptCreated('name');
$fake->assertNothingSent();

// Raw events for custom assertions
$events = $fake->events();
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
use Axyr\Langfuse\Dto\DatasetResponse;
use Axyr\Langfuse\Dto\DatasetItemResponse;
use Axyr\Langfuse\Dto\DatasetRunWithItemsResponse;
use Axyr\Langfuse\Dto\ObservationResponse;
use Axyr\Langfuse\Dto\ScoreResponse;

$fake = Langfuse::fake();

// Seed read responses
$fake->withScore(ScoreResponse::fromArray([
    'id' => 'score-abc', 'name' => 'accuracy', 'dataType' => 'NUMERIC', 'value' => 0.95,
]));
$fake->withObservation(ObservationResponse::fromArray(['id' => 'obs-1', 'type' => 'GENERATION']));
$fake->withMetrics([['name' => 'chat', 'count_count' => 42]]); // rows returned by queryMetrics()
$fake->withDataset(DatasetResponse::fromArray(['id' => 'ds-1', 'name' => 'qa-eval']));
$fake->withDatasetItem(DatasetItemResponse::fromArray(['id' => 'di-1', 'datasetName' => 'qa-eval']));
$fake->withDatasetRun(DatasetRunWithItemsResponse::fromArray([
    'run' => ['id' => 'run-1', 'name' => 'run-2026-01', 'datasetName' => 'qa-eval'],
    'datasetRunItems' => [],
]));

// Reads now return the seeded data
expect(Langfuse::getScore('score-abc')?->value)->toBe(0.95);

// Assert on creates performed against the fake
$fake->assertDatasetCreated('qa-eval')
    ->assertDatasetItemCreated('qa-eval')
    ->assertDatasetRunItemCreated('run-2026-01');
```

Seeders: `withScore()`, `withObservation()`, `withMetrics(array $rows)`,
`withDataset()`, `withDatasetItem()`, `withDatasetRun()`.
Read-side assertions: `assertDatasetCreated(?string $name)`,
`assertDatasetItemCreated(?string $datasetName)`,
`assertDatasetRunItemCreated(?string $runName)`.
Each assertion (and `with*` seeder) returns `$this` for chaining. Unseeded reads
return `null`, matching the resilient behaviour of the real clients.

## Disabling tracing in tests

Set `LANGFUSE_ENABLED=false` in `.env.testing`. The SDK swaps in a no-op batcher - nothing queued, nothing sent, no code changes needed.

---

Previous: [Batching and Flushing](batching-and-flushing.md) | Next: [Architecture](architecture.md)
