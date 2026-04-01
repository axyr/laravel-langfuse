<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Contracts;

use Axyr\Langfuse\Dto\CreatePromptBody;
use Axyr\Langfuse\Dto\PromptListResponse;

interface PromptApiClientInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function get(string $name, ?int $version = null, ?string $label = null): ?array;

    public function create(CreatePromptBody $body): ?PromptInterface;

    public function list(?string $name = null, ?string $label = null, ?int $page = null, ?int $limit = null): ?PromptListResponse;
}
