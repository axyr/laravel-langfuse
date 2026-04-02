[Back to documentation](README.md)

# Batching and Flushing

## Auto-flush

Events batch up and flush when the queue hits the `LANGFUSE_FLUSH_AT` threshold. They also flush automatically when Laravel terminates.

To flush manually:

```php
Langfuse::flush();
```

## Queued batching

By default, events are sent synchronously during the request. To dispatch them as queued jobs instead:

```env
LANGFUSE_QUEUE=langfuse
```

The SDK dispatches a `SendIngestionBatchJob` to your Laravel queue instead of making HTTP calls directly. You get persistence, automatic retries, and non-blocking flushes - all managed by your existing queue infrastructure.

## Checking if tracing is enabled

```php
if (Langfuse::isEnabled()) {
    // tracing is active
}
```

---

Previous: [Middleware](middleware.md) | Next: [Testing](testing.md)
