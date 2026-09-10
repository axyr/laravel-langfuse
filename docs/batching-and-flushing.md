[Back to documentation](README.md)

# Batching and Flushing

## What gets batched

The batcher holds two kinds of item, which go to two different endpoints:

| Item | Endpoint |
|---|---|
| Completed observations (root spans, spans, generations, events) | `POST /api/public/otel/v1/traces` (OTLP/JSON) |
| Scores | `POST /api/public/ingestion` as `score-create` events |

An observation only reaches the batcher when it ends, so nothing is queued for work that is still in flight.

## Auto-flush

The queue flushes when it hits the `LANGFUSE_FLUSH_AT` threshold (default 10). The threshold counts **completed observations and scores**, not raw events: a trace with one generation is two items, not the three create/update events v3 used to send.

The queue also flushes when Laravel terminates. That hook first ends every observation that is still open, so a request that forgot to call `end()` still produces complete data.

To flush manually:

```php
Langfuse::flush();
```

To end everything still open and then flush - what the terminate hook does, and what a queue worker, an Octane server or a long-running CLI command should call at the end of its unit of work:

```php
Langfuse::shutdown();
```

## Request size

Langfuse caps an OTLP request at 5 MB. The batcher splits a flush into as many requests as it needs to stay below 4 MB of serialised spans. A single observation larger than that is sent on its own and logged as a warning; the server decides what to do with it. Base64 blobs in `input`/`output` count against that budget.

## Compression

Off by default. Turn it on to gzip the OTLP request body:

```env
LANGFUSE_COMPRESSION=true
```

## Queued batching

By default, batches are sent synchronously during the request. To dispatch them as queued jobs instead:

```env
LANGFUSE_QUEUE=langfuse
```

The SDK then dispatches `SendOtelBatchJob` for the observations and `SendIngestionBatchJob` for the scores, onto that queue. You get persistence, retries, and non-blocking flushes from your existing queue infrastructure. The 4 MB split happens before dispatch, so a queued payload stays bounded.

Both jobs try three times with a `10, 60, 300` second backoff. `SendOtelBatchJob` only retries what the OTLP spec says may be retried:

- `429` releases the job for the `Retry-After` delay Langfuse sent.
- `502`, `503`, `504` and transport errors throw, so the worker retries with the backoff.
- `400` and friends are logged and dropped - the payload will not become valid.
- A partial success is logged and never retried, as the OTLP spec requires.

## Failure handling

Nothing here throws into your application. A failed flush logs a warning and drops the batch, and the queue is reset either way so a failure cannot pile up.

## Checking if tracing is enabled

```php
if (Langfuse::isEnabled()) {
    // tracing is active
}
```

When tracing is disabled the batcher is a no-op, so observations are built but never sent.

---

Previous: [Middleware](middleware.md) | Next: [Testing](testing.md)
