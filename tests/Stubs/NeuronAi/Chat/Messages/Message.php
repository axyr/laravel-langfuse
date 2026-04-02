<?php

declare(strict_types=1);

namespace NeuronAI\Chat\Messages;

class Message
{
    private ?Usage $usage = null;

    public function __construct(
        private string $role = 'user',
        private ?string $content = null,
    ) {}

    public function getRole(): string
    {
        return $this->role;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function getUsage(): ?Usage
    {
        return $this->usage;
    }

    public function setUsage(Usage $usage): static
    {
        $this->usage = $usage;

        return $this;
    }
}
