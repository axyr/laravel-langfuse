[Back to documentation](../README.md)

# Prism Integration

> See the [example project](https://github.com/axyr/laravel-langfuse-prism-examples) for a working demo.

Auto-instrumentation for [Prism](https://github.com/prism-php/prism) LLM calls. No code changes required.

## Setup

```env
LANGFUSE_PRISM_ENABLED=true
```

**Note:** Prism is automatically enabled when you enable [Laravel AI integration](laravel-ai.md), since Laravel AI uses Prism under the hood.

## What gets traced

The SDK wraps Prism's provider layer. Every `text()`, `structured()`, and `stream()` call automatically creates a trace and generation - including model parameters, token usage, and error tracking.

Other Prism methods (`embeddings()`, `images()`, etc.) pass through without tracing.

## Request grouping

If a trace is already current - from `LangfuseMiddleware` or from manual tracing - Prism generations nest under it, and it is left open for whoever owns it. Multiple Prism calls then share that single trace.

When there is no current trace, Prism creates one for the call, ends it as soon as the generation ends, with the response text as the trace output. That keeps each standalone Prism call a complete unit of work, which matters in queue workers and CLI commands where the terminate hook fires late or not at all.

If you want several standalone Prism calls in one trace, set a trace yourself:

```php
$trace = Langfuse::trace(new TraceBody(name: 'my-workflow'));
Langfuse::setCurrentTrace($trace);

// ... several Prism calls ...

$trace->end();
```

---

Previous: [Prompt Management](../prompt-management.md) | Next: [Laravel AI Integration](laravel-ai.md)
