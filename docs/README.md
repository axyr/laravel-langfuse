# Documentation

Laravel Langfuse SDK - full reference.

For installation and quick start, see the [main README](../README.md).

## Contents

- [Configuration](configuration.md) - environment variables, config publishing, disabling tracing
- [Tracing](tracing.md) - creating and updating traces, nesting observations
- [Generations](generations.md) - LLM generation tracking, usage and cost, error tracking
- [Spans and Events](spans-and-events.md) - spans for non-LLM work, lightweight event logging
- [Scores](scores.md) - numeric, boolean, and categorical quality scores
- [Prompt Management](prompt-management.md) - fetch, cache, compile, create, and list prompts
- **Integrations**
  - [Prism](integrations/prism.md) - auto-instrumentation for Prism LLM calls
  - [Laravel AI](integrations/laravel-ai.md) - auto-instrumentation for the Laravel AI SDK
  - [Neuron AI](integrations/neuron-ai.md) - observer-based tracing for Neuron AI agents
- [Middleware](middleware.md) - request middleware for automatic trace context
- [Batching and Flushing](batching-and-flushing.md) - auto-flush, manual flush, queued dispatch
- [Testing](testing.md) - fakes, assertions, disabling tracing in tests
- [Architecture](architecture.md) - system diagram, Octane compatibility
