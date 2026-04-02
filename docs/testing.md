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

## Disabling tracing in tests

Set `LANGFUSE_ENABLED=false` in `.env.testing`. The SDK swaps in a no-op batcher - nothing queued, nothing sent, no code changes needed.

---

Previous: [Batching and Flushing](batching-and-flushing.md) | Next: [Architecture](architecture.md)
