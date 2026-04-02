[Back to documentation](README.md)

# Architecture

```mermaid
graph LR
    App(["Your Application"])

    subgraph SDK ["Laravel Langfuse SDK"]
        direction LR

        subgraph Observability [" Observability "]
            direction TB
            Facade["LangfuseFacade"]
            Trace["Trace · Span · Generation"]
            Facade --> Trace
        end

        subgraph Batching [" Batching "]
            direction TB
            EB["EventBatcher\nsync"]
            QEB["QueuedEventBatcher\nasync via Laravel Queue"]
        end

        Prompt["Prompt Management\nfetch, cache & compile"]
    end

    LF(["Langfuse"])

    App -->|"Langfuse::trace()"| Facade
    App -->|"Langfuse::prompt()"| Prompt
    Trace --> EB & QEB
    EB & QEB --> LF
    Prompt --> LF
```

All DTOs are immutable readonly classes with auto-generated IDs and timestamps.

The SDK uses `EventBatcher` (sync) by default. Set `LANGFUSE_QUEUE` to switch to `QueuedEventBatcher`, which dispatches jobs instead of making HTTP calls directly.

API and batching failures are caught and logged. They never propagate exceptions to your application.

## Octane compatibility

The `EventBatcher` and `LangfuseClient` use scoped bindings that reset per request. No event leakage between requests.

Works out of the box with Octane, RoadRunner, and FrankenPHP. No additional configuration needed.

---

Previous: [Testing](testing.md)
