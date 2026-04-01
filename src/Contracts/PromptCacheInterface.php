<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Contracts;

interface PromptCacheInterface
{
    public function get(string $key): ?PromptInterface;

    public function put(string $key, PromptInterface $prompt, int $ttl): void;

    public function isExpired(string $key): bool;

    public function has(string $key): bool;
}
