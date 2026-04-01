<?php

declare(strict_types=1);

namespace Langfuse\Contracts;

use Langfuse\Dto\ScoreBody;
use Langfuse\Dto\TraceBody;
use Langfuse\Objects\LangfuseTrace;

interface LangfuseClientInterface
{
    public function trace(TraceBody $body): LangfuseTrace;

    public function currentTrace(): ?LangfuseTrace;

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

    /**
     * @param array<string, mixed> $prompt
     */
    public function createPrompt(array $prompt): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function listPrompts(?string $name = null, ?string $label = null, ?int $page = null, ?int $limit = null): ?array;
}
