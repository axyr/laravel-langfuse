[Back to documentation](../README.md)

# Neuron AI Integration

> See the [example project](https://github.com/axyr/laravel-langfuse-neuron-ai-examples) for a working demo.

Observer-based tracing for [Neuron AI](https://github.com/neuron-core/neuron-ai) agents.

## Setup

Enable tracing:

```env
LANGFUSE_NEURON_AI_ENABLED=true
```

Register the observer on your agent:

```php
use Axyr\Langfuse\NeuronAi\NeuronAiObserver;

$agent = new MyAgent;
$agent->observe(NeuronAiObserver::make());
$response = $agent->chat('Hello!');
```

Or register it in your agent's `setup()` method:

```php
use NeuronAI\Agent\Agent;
use Axyr\Langfuse\NeuronAi\NeuronAiObserver;

class MyAgent extends Agent
{
    protected function setup(): void
    {
        $this->observe(NeuronAiObserver::make());
    }
}
```

## What gets traced

The observer maps Neuron AI's event lifecycle to Langfuse:

- **Workflow start/end** - creates a trace, then sets the workflow state as its output and ends it
- **Inference** - creates a generation with input, response, and token usage, and ends it
- **Tool calls** - creates a span per tool with name and result, marked with the `tool` observation type, and ends it
- **RAG retrieval** - creates a span with query and document count, marked with the `retriever` observation type, and ends it
- **Errors** - records the exception on the trace and ends it

## Trace lifecycle

Since 0.4.0 an observation is only sent when it ends. The observer ends every generation and span it creates, and ends the trace on `workflow-end` or on an error - but only when it created that trace itself.

A trace it adopted - from `LangfuseMiddleware` or from your own `setCurrentTrace()` - is left open for its owner, so a workflow nested inside a larger unit of work does not close the enclosing trace.

## Request grouping

If you use `LangfuseMiddleware`, Neuron AI observations nest under the request trace automatically. In a queue worker or a CLI command, call `Langfuse::shutdown()` when the job is done.

---

Previous: [Laravel AI Integration](laravel-ai.md) | Next: [Middleware](../middleware.md)

Need help? See [Troubleshooting](../troubleshooting.md)
