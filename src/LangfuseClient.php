<?php

declare(strict_types=1);

namespace Axyr\Langfuse;

use Axyr\Langfuse\Concerns\CreatesIngestionEvents;
use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\EventBatcherInterface;
use Axyr\Langfuse\Contracts\LangfuseClientInterface;
use Axyr\Langfuse\Contracts\PromptApiClientInterface;
use Axyr\Langfuse\Contracts\PromptInterface;
use Axyr\Langfuse\Contracts\ScoreApiClientInterface;
use Axyr\Langfuse\Dto\CreatePromptBody;
use Axyr\Langfuse\Dto\PromptListResponse;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Enums\EventType;
use Axyr\Langfuse\Objects\LangfuseTrace;
use Axyr\Langfuse\Objects\NullLangfuseTrace;
use Axyr\Langfuse\Prompt\PromptManager;

class LangfuseClient implements LangfuseClientInterface
{
    use CreatesIngestionEvents;

    private LangfuseTrace $currentTrace;

    public function __construct(
        private readonly EventBatcherInterface $batcher,
        private readonly LangfuseConfig $config,
        private readonly PromptManager $promptManager,
        private readonly ScoreApiClientInterface $scoreApiClient,
        private readonly PromptApiClientInterface $promptApiClient,
    ) {
        $this->currentTrace = new NullLangfuseTrace();
    }

    public function trace(TraceBody $body): LangfuseTrace
    {
        return new LangfuseTrace(
            body: $body,
            batcher: $this->batcher,
        );
    }

    public function currentTrace(): LangfuseTrace
    {
        return $this->currentTrace;
    }

    public function setCurrentTrace(LangfuseTrace $trace): void
    {
        $this->currentTrace = $trace;
    }

    public function score(ScoreBody $body): void
    {
        $this->batcher->enqueue($this->createIngestionEvent(
            type: EventType::ScoreCreate,
            body: $body,
        ));
    }

    public function deleteScore(string $scoreId): bool
    {
        return $this->scoreApiClient->delete($scoreId);
    }

    public function flush(): void
    {
        $this->batcher->flush();
    }

    public function isEnabled(): bool
    {
        return $this->config->enabled;
    }

    public function prompt(
        string $name,
        ?int $version = null,
        ?string $label = null,
        string|array|null $fallback = null,
    ): PromptInterface {
        return $this->promptManager->get($name, $version, $label, $fallback);
    }

    public function createPrompt(CreatePromptBody $body): ?PromptInterface
    {
        return $this->promptApiClient->create($body);
    }

    public function listPrompts(?string $name = null, ?string $label = null, ?int $page = null, ?int $limit = null): ?PromptListResponse
    {
        return $this->promptApiClient->list($name, $label, $page, $limit);
    }
}
