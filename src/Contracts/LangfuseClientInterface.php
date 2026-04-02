<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Contracts;

use Axyr\Langfuse\Dto\CreatePromptBody;
use Axyr\Langfuse\Dto\PromptListResponse;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Objects\LangfuseTrace;

interface LangfuseClientInterface
{
    public function trace(TraceBody $body): LangfuseTrace;

    public function currentTrace(): LangfuseTrace;

    public function setCurrentTrace(LangfuseTrace $trace): void;

    public function score(ScoreBody $body): void;

    public function deleteScore(string $scoreId): bool;

    public function flush(): void;

    public function isEnabled(): bool;

    /**
     * @param string|array<int, array<string, string>>|null $fallback
     */
    public function prompt(
        string $name,
        ?int $version = null,
        ?string $label = null,
        string|array|null $fallback = null,
    ): PromptInterface;

    public function createPrompt(CreatePromptBody $body): ?PromptInterface;

    public function listPrompts(?string $name = null, ?string $label = null, ?int $page = null, ?int $limit = null): ?PromptListResponse;
}
