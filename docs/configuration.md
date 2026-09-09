[Back to documentation](README.md)

# Configuration

All configuration lives in `config/langfuse.php`. Override anything via environment variables:

| Variable | Default | Description |
|---|---|---|
| `LANGFUSE_PUBLIC_KEY` | `""` | Your Langfuse project public key |
| `LANGFUSE_SECRET_KEY` | `""` | Your Langfuse project secret key |
| `LANGFUSE_BASE_URL` | `https://cloud.langfuse.com` | API base URL (change for self-hosted) |
| `LANGFUSE_ENABLED` | `true` | Set to `false` to disable all tracing |
| `LANGFUSE_FLUSH_AT` | `10` | Number of events before auto-flushing |
| `LANGFUSE_REQUEST_TIMEOUT` | `15` | HTTP timeout in seconds |
| `LANGFUSE_PROMPT_CACHE_TTL` | `60` | Prompt cache TTL in seconds |
| `LANGFUSE_QUEUE` | `null` | Queue name for async batching (e.g. `langfuse`) |
| `LANGFUSE_TRACING_ENVIRONMENT` | `null` | Langfuse [environment](https://langfuse.com/docs/observability/features/environments) stamped on every event (e.g. `production`, `staging`) |
| `LANGFUSE_PRISM_ENABLED` | `false` | Auto-trace Prism LLM calls |
| `LANGFUSE_LARAVEL_AI_ENABLED` | `false` | Auto-trace Laravel AI SDK calls (also enables Prism tracing) |
| `LANGFUSE_NEURON_AI_ENABLED` | `false` | Auto-trace Neuron AI agents |

## Publishing the config file

```bash
php artisan vendor:publish --tag=langfuse-config
```

## Environments

Set `LANGFUSE_TRACING_ENVIRONMENT` (the same variable the official Langfuse SDKs use) to organize traces, observations, and scores from different deployments (for example `production`, `staging`, `local`) within a single Langfuse project.

Traces and scores created through the client are stamped with the configured environment unless they set their own. A trace passes its environment down to every span, generation, event and score created from it, so a per-trace override such as `new TraceBody(environment: 'staging')` keeps the whole trace together. This matters because **Langfuse environments are immutable after first ingestion**: setting the environment on a later trace update has no effect, so it must be present when the event is created.

Must match `^(?!langfuse)[a-z0-9-_]+$` (max 40 chars). Malformed values throw an `InvalidArgumentException` when the config is loaded, because Langfuse would otherwise reject every event. When unset, Langfuse assigns `default`.

## Disabling tracing

Set `LANGFUSE_ENABLED=false`. The SDK swaps in a no-op batcher - nothing is queued, nothing is sent, no code changes needed. Useful for local dev or test environments.

---

Next: [Tracing](tracing.md)
