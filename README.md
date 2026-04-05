<p><img src="art/banner.png" alt="Laravel Langfuse" style="border: none; outline: none; box-shadow: none;"></p>

# Laravel Langfuse

[![CI](https://github.com/axyr/laravel-langfuse/actions/workflows/ci.yml/badge.svg)](https://github.com/axyr/laravel-langfuse/actions/workflows/ci.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%209-brightgreen.svg?style=flat)](https://phpstan.org/)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4.svg?style=flat&logo=php)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12%20|%2013-FF2D20.svg?style=flat&logo=laravel)](https://laravel.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

[Langfuse](https://langfuse.com) is an open-source observability platform for LLM applications. It gives you a dashboard to trace every LLM call, track token usage and costs, manage prompt versions, and evaluate output quality - all in one place. It's self-hostable or available as a managed cloud service.

This package connects your Laravel app to Langfuse. Send traces, generations, scores, and prompts with a clean, idiomatic API - or let the auto-instrumentation do it for you.

```php
use Axyr\Langfuse\LangfuseFacade as Langfuse;

$trace = Langfuse::trace(new TraceBody(name: 'chat-request'));

$generation = $trace->generation(new GenerationBody(
    name: 'chat',
    model: 'gpt-4',
    input: [['role' => 'user', 'content' => 'Hello!']],
));

// After the LLM responds:
$generation->end(
    output: 'Hi there!',
    usage: new Usage(input: 12, output: 85, total: 97),
);
```

Events are batched and flushed automatically. Zero-code auto-instrumentation is available for [Laravel AI](https://laravel.com/docs/ai-sdk), [Prism](https://github.com/prism-php/prism), and [Neuron AI](https://github.com/neuron-core/neuron-ai).

<p><img src="art/langfuse-screenshot.png" alt="Langfuse trace view showing a RAG pipeline with nested spans, token usage, costs, and evaluation scores - all sent from Laravel"></p>

## Features

- **Full observability** - traces, spans, generations, events, and scores with automatic parent-child nesting
- **Prompt management** - fetch, cache, compile, create, and list prompts with stale-while-revalidate caching
- **Auto-instrumentation** - zero-code tracing for Prism, Laravel AI, and Neuron AI
- **Automatic batching** - events queued and sent in batches, with optional async dispatch via Laravel queues
- **Production-ready** - Octane compatible, graceful degradation, auto-flush on shutdown, testing fakes

## Installation

Requires PHP 8.2+ and Laravel 12 or 13.

**Langfuse Compatibility:** This package is compatible with both Langfuse v2 and v3. For self-hosted deployments, v3 introduces an asynchronous architecture with improved reliability and performance.

```bash
composer require axyr/laravel-langfuse
```

Add your Langfuse credentials to `.env`:

```env
LANGFUSE_PUBLIC_KEY=pk-lf-...
LANGFUSE_SECRET_KEY=sk-lf-...
```

Optionally publish the configuration file:

```bash
php artisan vendor:publish --tag=langfuse-config
```

## Examples

Working example projects for each integration:

- [Laravel AI + Langfuse](https://github.com/axyr/laravel-langfuse-ai-examples) - agents, tools, streaming, and scoring with the official Laravel AI SDK
- [Prism + Langfuse](https://github.com/axyr/laravel-langfuse-prism-examples) - text, structured output, and streaming with Prism
- [Neuron AI + Langfuse](https://github.com/axyr/laravel-langfuse-neuron-ai-examples) - agent workflows with Neuron AI

## Documentation

Full documentation in the [`docs/`](docs/README.md) directory:

- [Configuration](docs/configuration.md) - env vars, config publishing
- [Tracing](docs/tracing.md) - traces, updating, nesting observations
- [Generations](docs/generations.md) - LLM generation tracking, usage and cost
- [Spans and Events](docs/spans-and-events.md) - non-LLM work, event logging
- [Scores](docs/scores.md) - numeric, boolean, categorical scores
- [Prompt Management](docs/prompt-management.md) - fetch, cache, compile, create
- [Integrations](docs/integrations/prism.md) - Prism, Laravel AI, Neuron AI
- [Middleware](docs/middleware.md) - request trace context
- [Batching and Flushing](docs/batching-and-flushing.md) - flush control, queued dispatch
- [Testing](docs/testing.md) - fakes and assertions
- [Architecture](docs/architecture.md) - system diagram, Octane compatibility
- [Troubleshooting](docs/troubleshooting.md) - Langfuse v3 compatibility, common issues

## Contributing

Contributions welcome. Open an issue first to discuss what you'd like to change.

```bash
composer test        # Run tests
composer pint        # Fix code style
```

## License

MIT

## Author

Built by [Martijn van Nieuwenhoven](https://martijnvannieuwenhoven.com) - Laravel developer specializing in AI integrations and observability tooling.