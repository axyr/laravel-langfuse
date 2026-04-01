<?php

declare(strict_types=1);

namespace Langfuse;

use Langfuse\Concerns\CreatesIngestionEvents;
use Langfuse\Config\LangfuseConfig;
use Langfuse\Contracts\EventBatcherInterface;
use Langfuse\Contracts\LangfuseClientInterface;
use Langfuse\Contracts\PromptApiClientInterface;
use Langfuse\Contracts\PromptInterface;
use Langfuse\Contracts\ScoreApiClientInterface;
use Langfuse\Dto\ScoreBody;
use Langfuse\Dto\TraceBody;
use Langfuse\Enums\EventType;
use Langfuse\Objects\LangfuseTrace;
use Langfuse\Prompt\PromptManager;

class LangfuseClient implements LangfuseClientInterface
{
    use CreatesIngestionEvents;

    private ?LangfuseTrace $currentTrace = null;

    public function __construct(
        private readonly EventBatcherInterface $batcher,
        private readonly LangfuseConfig $config,
        private readonly PromptManager $promptManager,
        private readonly ScoreApiClientInterface $scoreApiClient,
        private readonly PromptApiClientInterface $promptApiClient,
    ) {}

    public function trace(TraceBody $body): LangfuseTrace
    {
        return new LangfuseTrace(
            body: $body,
            batcher: $this->batcher,
        );
    }

    public function currentTrace(): ?LangfuseTrace
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

    /**
     * @param array<string, mixed> $prompt
     * @return array<string, mixed>|null
     */
    public function createPrompt(array $prompt): ?array
    {
        return $this->promptApiClient->create($prompt);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function listPrompts(?string $name = null, ?string $label = null, ?int $page = null, ?int $limit = null): ?array
    {
        return $this->promptApiClient->list($name, $label, $page, $limit);
    }
}
