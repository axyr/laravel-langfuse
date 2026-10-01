<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

use Axyr\Langfuse\Contracts\PromptInterface;
use Axyr\Langfuse\Contracts\SerializableInterface;
use Axyr\Langfuse\Enums\ObservationLevel;

/**
 * A custom `id` is normalised into a 16 hex character OTLP span id and a custom
 * `traceId` into a 32 hex character trace id.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
readonly class GenerationBody implements SerializableInterface
{
    public string $id;

    public ?string $traceId;

    public ?string $parentObservationId;

    /**
     * @param array<string, mixed>|null $metadata
     * @param array<string, mixed>|null $modelParameters
     */
    public function __construct(
        ?string $id = null,
        ?string $traceId = null,
        public ?string $name = null,
        public ?string $startTime = null,
        public ?string $endTime = null,
        public ?string $completionStartTime = null,
        public mixed $input = null,
        public mixed $output = null,
        public ?array $metadata = null,
        public ?ObservationLevel $level = null,
        public ?string $statusMessage = null,
        ?string $parentObservationId = null,
        public ?string $version = null,
        public ?string $model = null,
        public ?array $modelParameters = null,
        public ?Usage $usage = null,
        public ?string $promptName = null,
        public ?int $promptVersion = null,
        public ?string $environment = null,
    ) {
        $this->id = IdGenerator::normalizeObservationId($id);
        $this->traceId = $traceId === null ? null : IdGenerator::normalizeTraceId($traceId);
        $this->parentObservationId = $parentObservationId === null
            ? null
            : IdGenerator::normalizeObservationId($parentObservationId);
    }

    public function withTraceId(string $traceId): self
    {
        return $this->withContext($traceId, $this->parentObservationId);
    }

    public function withContext(string $traceId, ?string $parentObservationId): self
    {
        return $this->copy(traceId: $traceId, parentObservationId: $parentObservationId);
    }

    /**
     * Returns a copy stamped with the given environment when this body has none.
     */
    public function withEnvironment(?string $environment): self
    {
        if ($environment === null || $this->environment !== null) {
            return $this;
        }

        return $this->copy(environment: $environment);
    }

    /**
     * Returns a copy stamped with the given start time when this body has none.
     */
    public function startedAt(string $startTime): self
    {
        if ($this->startTime !== null) {
            return $this;
        }

        return $this->copy(startTime: $startTime);
    }

    /**
     * Returns a copy linked to the given managed prompt when this body is not
     * already linked to one. Fallback prompts are never linked.
     */
    public function withPrompt(?PromptInterface $prompt): self
    {
        if ($prompt === null || $prompt->isFallback() || $this->promptName !== null) {
            return $this;
        }

        return $this->copy(
            promptName: $prompt->getName(),
            promptVersion: $prompt->getVersion(),
        );
    }

    /**
     * Returns the finished observation: what `end()` hands to the batcher.
     */
    public function completed(
        string $endTime,
        mixed $output = null,
        ?Usage $usage = null,
        ?string $statusMessage = null,
        ?ObservationLevel $level = null,
    ): self {
        return $this->copy(
            endTime: $endTime,
            output: $output ?? $this->output,
            statusMessage: $statusMessage ?? $this->statusMessage,
            level: $level ?? $this->level,
            usage: $usage ?? $this->usage,
        );
    }

    /**
     * One-to-one field copy; every field has to be named.
     *
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    private function copy(
        ?string $traceId = null,
        ?string $parentObservationId = null,
        ?string $environment = null,
        ?string $startTime = null,
        ?string $endTime = null,
        mixed $output = null,
        ?string $statusMessage = null,
        ?ObservationLevel $level = null,
        ?Usage $usage = null,
        ?string $promptName = null,
        ?int $promptVersion = null,
    ): self {
        return new self(
            id: $this->id,
            traceId: $traceId ?? $this->traceId,
            name: $this->name,
            startTime: $startTime ?? $this->startTime,
            endTime: $endTime ?? $this->endTime,
            completionStartTime: $this->completionStartTime,
            input: $this->input,
            output: $output ?? $this->output,
            metadata: $this->metadata,
            level: $level ?? $this->level,
            statusMessage: $statusMessage ?? $this->statusMessage,
            parentObservationId: $parentObservationId ?? $this->parentObservationId,
            version: $this->version,
            model: $this->model,
            modelParameters: $this->modelParameters,
            usage: $usage ?? $this->usage,
            promptName: $promptName ?? $this->promptName,
            promptVersion: $promptVersion ?? $this->promptVersion,
            environment: $environment ?? $this->environment,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'traceId' => $this->traceId,
            'name' => $this->name,
            'startTime' => $this->startTime,
            'endTime' => $this->endTime,
            'completionStartTime' => $this->completionStartTime,
            'input' => $this->input,
            'output' => $this->output,
            'metadata' => $this->metadata,
            'level' => $this->level?->value,
            'statusMessage' => $this->statusMessage,
            'parentObservationId' => $this->parentObservationId,
            'version' => $this->version,
            'model' => $this->model,
            'modelParameters' => $this->modelParameters,
            'usage' => $this->usage?->toArray(),
            'promptName' => $this->promptName,
            'promptVersion' => $this->promptVersion,
            'environment' => $this->environment,
        ], fn(mixed $value): bool => $value !== null);
    }
}
