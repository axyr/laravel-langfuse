[Back to documentation](../README.md)

# Laravel AI Integration

> See the [example project](https://github.com/axyr/laravel-langfuse-ai-examples) for a working demo.

Auto-instrumentation for the official [Laravel AI SDK](https://laravel.com/docs/ai-sdk). No code changes required.

## Setup

```env
LANGFUSE_LARAVEL_AI_ENABLED=true
```

**Note:** Laravel AI uses Prism under the hood. When you enable Laravel AI tracing, Prism tracing is automatically enabled as well - you don't need to set `LANGFUSE_PRISM_ENABLED=true` separately.

## What gets traced

The SDK listens to Laravel AI's event system:

- **Agent prompts** - each `prompt()` or `stream()` call creates a trace and generation with model name, token usage, input, and output
- **Tool invocations** - each tool call creates a span under the agent's trace with arguments and results, marked with the `tool` observation type

## Users and sessions

Agent traces are tagged with `userId` and `sessionId` so the Langfuse Users and Sessions views can group them. Both come from Laravel AI's own conversation state, so nothing needs to be implemented on the agent:

- **`sessionId`** is the conversation id of an agent that uses `RemembersConversations`. A brand new conversation only gets its id after the first run, so the trace is tagged when the run completes.
- **`userId`** is the conversation participant passed to `forUser()`, `continue()` or `continueLastConversation()`. This also works for queued agent runs, where no authenticated user exists. Agents without a participant fall back to the [package-wide default](../configuration.md#users-and-sessions), the authenticated user.

```php
class SupportAgent implements Agent
{
    use Promptable, RemembersConversations;
}

$response = (new SupportAgent)->forUser($user)->prompt('Hi');
// trace: userId = $user->getKey(), sessionId = $response->conversationId
```

When agent calls nest under a request trace from `LangfuseMiddleware` or a manual trace, the session and user are added to that trace as well. Its output is left alone: only traces the integration created itself receive the agent's response text as output.

Set `LANGFUSE_SESSION_TRACING=false` or `LANGFUSE_USER_TRACING=false` to stop forwarding either identifier. For a session that is not a Laravel AI conversation (a support ticket, a checkout), bind a custom `TraceContextResolverInterface` as described in the configuration docs.

## Trace lifecycle

Since 0.4.0 an observation is only sent when it ends, so the integration ends what it creates:

- The generation and every tool span end as soon as their event fires.
- A trace the integration created itself is ended when the invocation completes, and the current trace is reset, so the next invocation starts a fresh trace.
- A trace it adopted - from `LangfuseMiddleware` or from your own `setCurrentTrace()` - is never ended by the integration. It stays open for its owner, who ends it or relies on the application shutdown hook.

## Request grouping

If a trace is already current, agent generations nest under it and multiple agent calls within the same request share it. With `LangfuseMiddleware` that happens automatically.

## Combining with manual tracing

Set a custom trace before the agent call. Laravel AI generations will nest under it:

```php
use Axyr\Langfuse\LangfuseFacade as Langfuse;
use Axyr\Langfuse\Dto\TraceBody;

$trace = Langfuse::trace(new TraceBody(
    name: 'my-custom-workflow',
    userId: 'user-42',
));
Langfuse::setCurrentTrace($trace);

// This agent call now appears under your custom trace
$response = (new MyAgent)->prompt('Do something');

// You own this trace, so you end it
$trace->end(output: $response->text);
```

In a queue worker or a CLI command, call `Langfuse::shutdown()` when the job is done: it ends anything still open and flushes.

---

Previous: [Prism Integration](prism.md) | Next: [Neuron AI Integration](neuron-ai.md)
