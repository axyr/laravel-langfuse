[Back to documentation](../README.md)

# Laravel AI Integration

Auto-instrumentation for the official [Laravel AI SDK](https://laravel.com/docs/ai-sdk). No code changes required.

## Setup

```env
LANGFUSE_LARAVEL_AI_ENABLED=true
```

**Note:** Laravel AI uses Prism under the hood. When you enable Laravel AI tracing, Prism tracing is automatically enabled as well - you don't need to set `LANGFUSE_PRISM_ENABLED=true` separately.

## What gets traced

The SDK listens to Laravel AI's event system:

- **Agent prompts** - each `prompt()` or `stream()` call creates a trace and generation with model name, token usage, input, and output
- **Tool invocations** - each tool call creates a span under the agent's trace with arguments and results

## Request grouping

Multiple agent calls within the same request share a single trace. If you use `LangfuseMiddleware`, agent generations nest under the request trace automatically.

## Combining with manual tracing

Set a custom trace before the agent call. Laravel AI generations will nest under it:

```php
use Axyr\Langfuse\LangfuseFacade as Langfuse;
use Axyr\Langfuse\Dto\TraceBody;

$trace = Langfuse::trace(new TraceBody(
    name: 'my-custom-workflow',
    userId: 'user-42',
));
Langfuse::setCurrentTrace($trace);

// This agent call now appears under your custom trace
$response = (new MyAgent)->prompt('Do something');
```

---

Previous: [Prism Integration](prism.md) | Next: [Neuron AI Integration](neuron-ai.md)
