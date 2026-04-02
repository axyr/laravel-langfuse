[Back to documentation](../README.md)

# Prism Integration

Auto-instrumentation for [Prism](https://github.com/prism-php/prism) LLM calls. No code changes required.

## Setup

```env
LANGFUSE_PRISM_ENABLED=true
```

That's it.

## What gets traced

The SDK wraps Prism's provider layer. Every `text()`, `structured()`, and `stream()` call automatically creates a trace and generation - including model parameters, token usage, and error tracking.

Other Prism methods (`embeddings()`, `images()`, etc.) pass through without tracing.

## Request grouping

Multiple Prism calls within the same request share a single trace. If you use `LangfuseMiddleware`, Prism generations nest under the request trace automatically.

---

Previous: [Prompt Management](../prompt-management.md) | Next: [Laravel AI Integration](laravel-ai.md)
