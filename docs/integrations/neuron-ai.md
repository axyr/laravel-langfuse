[Back to documentation](../README.md)

# Neuron AI Integration

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

- **Workflow start/end** - creates a trace with workflow state as output
- **Inference** - creates a generation with input, response, and token usage
- **Tool calls** - creates a span per tool with name and result
- **RAG retrieval** - creates a span with query and document count
- **Errors** - records exception details on the trace

## Request grouping

If you use `LangfuseMiddleware`, Neuron AI observations nest under the request trace automatically.

---

Previous: [Laravel AI Integration](laravel-ai.md) | Next: [Middleware](../middleware.md)
