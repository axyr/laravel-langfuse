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

The middleware ends the trace's root observation once the response is ready, with the response status as the trace output. Work that happens after the response is sent - `terminate()` middleware, deferred callbacks - is covered by the application shutdown hook, which ends whatever is still open before the final flush.

## Manual trace context

Set a trace manually for the same nesting behavior:

```php
$trace = Langfuse::trace(new TraceBody(name: 'my-job'));
Langfuse::setCurrentTrace($trace);

// Subsequent observations nest under this trace

$trace->end(output: $result);
```

You own a trace you set yourself: the integrations adopt it but never end it. End it when the unit of work is done, or call `Langfuse::shutdown()` - in a queue worker or a long-running command there is no terminate hook to fall back on.

Use `Langfuse::currentTrace()` to get the current request trace. Returns a `NullLangfuseTrace` no-op instance if none is set.

---

Previous: [Neuron AI Integration](integrations/neuron-ai.md) | Next: [Batching and Flushing](batching-and-flushing.md)
