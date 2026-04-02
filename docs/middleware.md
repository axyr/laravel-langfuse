[Back to documentation](README.md)

# Request Middleware

The `LangfuseMiddleware` auto-creates a trace for each HTTP request. All observations during that request nest under it.

## Setup

```php
use Axyr\Langfuse\Http\Middleware\LangfuseMiddleware;

Route::middleware(LangfuseMiddleware::class)->group(function () {
    Route::post('/chat', ChatController::class);
});
```

The trace name is set to the route name, or `METHOD /path` as fallback. The authenticated user ID and request metadata are captured automatically.

## Manual trace context

Set a trace manually for the same nesting behavior:

```php
$trace = Langfuse::trace(new TraceBody(name: 'my-job'));
Langfuse::setCurrentTrace($trace);

// Subsequent observations nest under this trace
```

Use `Langfuse::currentTrace()` to get the current request trace. Returns a `NullLangfuseTrace` no-op instance if none is set.

---

Previous: [Neuron AI Integration](integrations/neuron-ai.md) | Next: [Batching and Flushing](batching-and-flushing.md)
