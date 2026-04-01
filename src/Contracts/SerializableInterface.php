<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Contracts;

interface SerializableInterface
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
