<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

use Axyr\Langfuse\Contracts\SerializableInterface;

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
    ) {
        $this->id = $id ?? IdGenerator::uuid();
        $this->timestamp = $timestamp ?? IdGenerator::timestamp();
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
