<?php

declare(strict_types=1);

namespace Langfuse;

use Langfuse\Concerns\CreatesIngestionEvents;
use Langfuse\Config\LangfuseConfig;
use Langfuse\Contracts\EventBatcherInterface;
use Langfuse\Contracts\LangfuseClientInterface;
use Langfuse\Dto\ScoreBody;
use Langfuse\Dto\TraceBody;
use Langfuse\Enums\EventType;
use Langfuse\Objects\LangfuseTrace;

class LangfuseClient implements LangfuseClientInterface
{
    use CreatesIngestionEvents;
    public function __construct(
        private readonly EventBatcherInterface $batcher,
        private readonly LangfuseConfig $config,
    ) {}

    public function trace(TraceBody $body): LangfuseTrace
    {
        return new LangfuseTrace(
            body: $body,
            batcher: $this->batcher,
        );
    }

    public function score(ScoreBody $body): void
    {
        $this->batcher->enqueue($this->createIngestionEvent(
            type: EventType::ScoreCreate,
            body: $body,
        ));
    }

    public function flush(): void
    {
        $this->batcher->flush();
    }

    public function isEnabled(): bool
    {
        return $this->config->enabled;
    }
}
