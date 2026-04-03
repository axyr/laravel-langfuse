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
| `LANGFUSE_PRISM_ENABLED` | `false` | Auto-trace Prism LLM calls |
| `LANGFUSE_LARAVEL_AI_ENABLED` | `false` | Auto-trace Laravel AI SDK calls (also enables Prism tracing) |
| `LANGFUSE_NEURON_AI_ENABLED` | `false` | Auto-trace Neuron AI agents |

## Publishing the config file

```bash
php artisan vendor:publish --tag=langfuse-config
```

## Disabling tracing

Set `LANGFUSE_ENABLED=false`. The SDK swaps in a no-op batcher - nothing is queued, nothing is sent, no code changes needed. Useful for local dev or test environments.

---

Next: [Tracing](tracing.md)
