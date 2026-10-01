<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

use Axyr\Langfuse\Contracts\SerializableInterface;

/**
 * The trace's root observation. A custom `id` is normalised into a 32 hex
 * character OTLP trace id, so `$trace->getId()` can differ from what was passed
 * in; use the returned value when linking scores or dataset items.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
readonly class TraceBody implements SerializableInterface
{
    public string $id;

    public string $timestamp;

    /**
     * @param array<string, mixed>|null $metadata
     * @param array<string>|null $tags
     */
    public function __construct(
        ?string $id = null,
        public ?string $name = null,
        public ?string $userId = null,
        public ?string $sessionId = null,
        public ?string $release = null,
        public ?string $version = null,
        public mixed $input = null,
        public mixed $output = null,
        public ?array $metadata = null,
        public ?array $tags = null,
        public ?bool $public = null,
        ?string $timestamp = null,
        public ?string $environment = null,
        public ?ExperimentContext $experiment = null,
        public ?ExperimentItemContext $experimentItem = null,
    ) {
        $this->id = IdGenerator::normalizeTraceId($id);
        $this->timestamp = $timestamp ?? IdGenerator::timestamp();
    }

    /**
     * Returns a copy stamped with the given environment when this body has none.
     */
    public function withEnvironment(?string $environment): self
    {
        if ($environment === null || $this->environment !== null) {
            return $this;
        }

        return $this->copy($this->userId, $this->sessionId, $environment);
    }

    /**
     * Returns a copy stamped with the given release when this body has none.
     */
    public function withRelease(?string $release): self
    {
        if ($release === null || $this->release !== null) {
            return $this;
        }

        return $this->mergedWith(new self(id: $this->id, timestamp: $this->timestamp, release: $release));
    }

    /**
     * Returns a copy with the given userId when this body has none.
     */
    public function withUserId(?string $userId): self
    {
        if ($userId === null || $this->userId !== null) {
            return $this;
        }

        return $this->copy($userId, $this->sessionId, $this->environment);
    }

    /**
     * Returns a copy with the given sessionId when this body has none.
     */
    public function withSessionId(?string $sessionId): self
    {
        if ($sessionId === null || $this->sessionId !== null) {
            return $this;
        }

        return $this->copy($this->userId, $sessionId, $this->environment);
    }

    /**
     * Returns a copy linked to the given experiment and dataset item.
     */
    public function forExperimentItem(ExperimentContext $experiment, ?ExperimentItemContext $item = null): self
    {
        return $this->mergedWith(new self(
            id: $this->id,
            timestamp: $this->timestamp,
            experiment: $experiment,
            experimentItem: $item,
        ));
    }

    /**
     * Folds an update into this body: every field the update sets wins, fields it
     * leaves null keep their current value, and metadata is merged key by key.
     * The trace id and timestamp are always preserved.
     */
    public function mergedWith(self $update): self
    {
        return new self(
            id: $this->id,
            name: $update->name ?? $this->name,
            userId: $update->userId ?? $this->userId,
            sessionId: $update->sessionId ?? $this->sessionId,
            release: $update->release ?? $this->release,
            version: $update->version ?? $this->version,
            input: $update->input ?? $this->input,
            output: $update->output ?? $this->output,
            metadata: self::mergeMetadata($this->metadata, $update->metadata),
            tags: $update->tags ?? $this->tags,
            public: $update->public ?? $this->public,
            timestamp: $this->timestamp,
            environment: $update->environment ?? $this->environment,
            experiment: $update->experiment ?? $this->experiment,
            experimentItem: $update->experimentItem ?? $this->experimentItem,
        );
    }

    /**
     * @param array<string, mixed>|null $current
     * @param array<string, mixed>|null $update
     * @return array<string, mixed>|null
     */
    private static function mergeMetadata(?array $current, ?array $update): ?array
    {
        if ($current === null) {
            return $update;
        }

        if ($update === null) {
            return $current;
        }

        return array_merge($current, $update);
    }

    private function copy(?string $userId, ?string $sessionId, ?string $environment): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            userId: $userId,
            sessionId: $sessionId,
            release: $this->release,
            version: $this->version,
            input: $this->input,
            output: $this->output,
            metadata: $this->metadata,
            tags: $this->tags,
            public: $this->public,
            timestamp: $this->timestamp,
            environment: $environment,
            experiment: $this->experiment,
            experimentItem: $this->experimentItem,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'timestamp' => $this->timestamp,
            'name' => $this->name,
            'userId' => $this->userId,
            'sessionId' => $this->sessionId,
            'release' => $this->release,
            'version' => $this->version,
            'input' => $this->input,
            'output' => $this->output,
            'metadata' => $this->metadata,
            'tags' => $this->tags,
            'public' => $this->public,
            'environment' => $this->environment,
        ], fn(mixed $value): bool => $value !== null);
    }
}
