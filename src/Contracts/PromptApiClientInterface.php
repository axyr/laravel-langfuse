<?php

declare(strict_types=1);

namespace Langfuse\Contracts;

interface PromptApiClientInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function get(string $name, ?int $version = null, ?string $label = null): ?array;

    /**
     * @param array<string, mixed> $prompt
     * @return array<string, mixed>|null
     */
    public function create(array $prompt): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function list(?string $name = null, ?string $label = null, ?int $page = null, ?int $limit = null): ?array;
}
