<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Prompt;

use Axyr\Langfuse\Contracts\PromptApiClientInterface;
use Axyr\Langfuse\Contracts\PromptCacheInterface;
use Axyr\Langfuse\Contracts\PromptInterface;
use Axyr\Langfuse\Dto\PromptFactory;
use Axyr\Langfuse\Exceptions\PromptNotFoundException;

class PromptManager
{
    public function __construct(
        private readonly PromptApiClientInterface $apiClient,
        private readonly PromptCacheInterface $cache,
        private readonly int $cacheTtl = 60,
    ) {}

    /**
     * @param string|array<int, array<string, string>>|null $fallback
     */
    public function get(
        string $name,
        ?int $version = null,
        ?string $label = null,
        string|array|null $fallback = null,
    ): PromptInterface {
        $cacheKey = $this->buildCacheKey($name, $version, $label);

        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $fetched = $this->fetchAndCache($cacheKey, $name, $version, $label);
        if ($fetched !== null) {
            return $fetched;
        }

        return $this->resolveStaleOrFallback($cacheKey, $name, $fallback);
    }

    private function getFromCache(string $cacheKey): ?PromptInterface
    {
        if (! $this->cache->has($cacheKey) || $this->cache->isExpired($cacheKey)) {
            return null;
        }

        return $this->cache->get($cacheKey);
    }

    private function fetchAndCache(
        string $cacheKey,
        string $name,
        ?int $version,
        ?string $label,
    ): ?PromptInterface {
        $data = $this->apiClient->get($name, $version, $label);

        if ($data === null) {
            return null;
        }

        $prompt = PromptFactory::fromApiResponse($data);
        $this->cache->put($cacheKey, $prompt, $this->cacheTtl);

        return $prompt;
    }

    /**
     * @param string|array<int, array<string, string>>|null $fallback
     */
    private function resolveStaleOrFallback(
        string $cacheKey,
        string $name,
        string|array|null $fallback,
    ): PromptInterface {
        $stale = $this->cache->has($cacheKey) ? $this->cache->get($cacheKey) : null;

        if ($stale !== null) {
            return $stale;
        }

        if ($fallback !== null) {
            return $this->buildFallback($name, $fallback);
        }

        throw PromptNotFoundException::forName($name);
    }

    private function buildCacheKey(string $name, ?int $version, ?string $label): string
    {
        return implode(':', array_filter([
            'prompt',
            $name,
            $version !== null ? "v{$version}" : null,
            $label !== null ? "l:{$label}" : null,
        ]));
    }

    /**
     * @param string|array<int, array<string, string>> $fallback
     */
    private function buildFallback(string $name, string|array $fallback): PromptInterface
    {
        if (is_array($fallback)) {
            return PromptFactory::fallbackChat($name, $fallback);
        }

        return PromptFactory::fallbackText($name, $fallback);
    }
}
