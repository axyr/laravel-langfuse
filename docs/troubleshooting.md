[Back to documentation](README.md)

# Troubleshooting

Common issues and solutions when using Laravel Langfuse.

## Langfuse v4 tracing

Since 0.4.0 the package writes observations to the OTLP endpoint
`POST /api/public/otel/v1/traces` and only sends scores to
`POST /api/public/ingestion`. Langfuse Cloud removes the deprecated ingestion and
read endpoints on **2026-11-16**.

### Nothing appears in Langfuse

**In v4 an observation is only sent when it ends.** Nothing is queued while the
work is in flight, and an observation that never ends is never exported.

- Call `end()` on the spans, generations and traces you create.
- The application terminate hook ends whatever is still open, so HTTP requests
  are covered without extra code.
- In a queue worker, an Octane server or a long-running command, call
  `Langfuse::shutdown()` at the end of the unit of work: it ends every open
  observation and flushes.
- An observation ended by the shutdown hook is marked `WARNING` with the status
  message `ended by shutdown`. Seeing those in Langfuse means the code path is
  missing an explicit `end()`.

### Traces show up 10-15 minutes late

Langfuse needs the `x-langfuse-ingestion-version: 4` header to write straight
into the v4 model; without it OpenTelemetry data can be delayed by 10-15
minutes. The package always sends it, so a delay means something stripped the
header in transit - usually a proxy or a WAF between your app and Langfuse.
Check that your egress proxy forwards `x-langfuse-*` headers.

### `Langfuse OTLP partial success`

```
[2026-09-10] local.WARNING: Langfuse OTLP partial success
{"rejectedSpans":2,"errorMessage":"..."}
```

Langfuse accepted the request but rejected some spans. The OTLP spec forbids
retrying a partial success, so the package logs it and moves on. The
`errorMessage` says which spans were rejected and why - usually a malformed
attribute value.

### `Langfuse OTLP export failed`

```
[2026-09-10] local.WARNING: Langfuse OTLP export failed
{"status":400,"retryable":false,"body":"..."}
```

- `400`, `401`, `403`, `404`, `405` are not retried: the payload will not become
  valid. Check credentials for a `401`/`403` and the base URL for a `404`.
- `429`, `502`, `503`, `504` and transport errors are retryable. On the queued
  path the job retries three times with a `10, 60, 300` second backoff, and a
  `429` is released for exactly the `Retry-After` delay Langfuse sent.

### Rate limits

Langfuse Cloud limits ingestion to 1,000 requests per minute on Hobby, 4,000 on
Core and 20,000 on Pro and up. If you hit them, batch more aggressively by
raising `LANGFUSE_FLUSH_AT` and move to the queue with `LANGFUSE_QUEUE`.

The Metrics API v2 is limited separately and much more tightly: 100 requests per
day on Hobby, 100 per hour on Core and 500 per hour on Pro and up.

### Request too large

Langfuse caps an OTLP request at 5 MB. The batcher splits a flush to stay below
4 MB and logs a warning for a single observation that exceeds it on its own:

```
[2026-09-10] local.WARNING: Langfuse observation exceeds the OTLP request limit
{"spanId":"...","bytes":5242880}
```

Base64 blobs in `input`/`output` are the usual cause. The package does not use
the Media API, so upload large payloads elsewhere and reference them.

### Which Langfuse versions work

| Surface | Requirement |
|---|---|
| Tracing (OTLP) | Cloud v3 and v4, self-hosted OSS 3.22.0+ |
| Scores (write, read v3) | Cloud v3 and v4, self-hosted v3+ |
| Observations v2, Metrics v2, Experiments | Langfuse v4 |

A self-hosted v4 in its default mode (`LANGFUSE_MIGRATION_V4_WRITE_MODE=events_only`)
returns `400` for every ingestion event type except `score-create`, which is
exactly what this package still sends there.

### Checking for errors

```bash
tail -f storage/logs/laravel.log | grep "Langfuse"
```

## General Issues

### Events not appearing

**Check if tracing is enabled:**
```bash
php artisan tinker
>>> config('langfuse.enabled')
```

**Check credentials:**
```bash
>>> config('langfuse.public_key')
>>> config('langfuse.secret_key')
```

**Force a manual flush:**
```php
use Axyr\Langfuse\LangfuseFacade as Langfuse;

// Ends everything still open, then flushes
Langfuse::shutdown();
```

### High memory usage

If you're seeing memory issues, reduce the flush threshold:

```env
LANGFUSE_FLUSH_AT=5  # Default is 10
```

Or enable queue-based batching:

```env
LANGFUSE_QUEUE=langfuse
```

### Auto-instrumentation not working

**Laravel AI not tracing:**
```env
LANGFUSE_LARAVEL_AI_ENABLED=true  # Must be explicitly enabled
```

**Prism not tracing:**
```env
LANGFUSE_PRISM_ENABLED=true  # Or enable Laravel AI (auto-enables Prism)
```

**Neuron AI not tracing:**
```env
LANGFUSE_NEURON_AI_ENABLED=true
```

### Testing issues

If tests are failing due to Langfuse API calls, use the fake:

```php
use Axyr\Langfuse\LangfuseFacade as Langfuse;

Langfuse::fake();

// Your test code...

Langfuse::assertNothingSent();
```

## Getting Help

If you're still experiencing issues:

1. **Enable debug logging** - Set `LOG_LEVEL=debug` in `.env`
2. **Check compatibility** - Verify PHP 8.2+, Laravel 12/13, and a Langfuse version from the table above
3. **Review logs** - Check both Laravel and Langfuse logs
4. **Create an issue** - [GitHub Issues](https://github.com/axyr/laravel-langfuse/issues) with:
   - Package version (`composer show axyr/laravel-langfuse`)
   - Langfuse version (cloud or self-hosted version)
   - Error messages from logs
   - Minimal reproduction steps

---

Previous: [Neuron AI Integration](integrations/neuron-ai.md)
