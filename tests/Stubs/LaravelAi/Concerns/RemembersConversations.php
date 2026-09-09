<?php

declare(strict_types=1);

namespace Laravel\Ai\Concerns;

trait RemembersConversations
{
    protected ?string $conversationId = null;

    protected ?object $conversationUser = null;

    public function forParticipant(object $participant): static
    {
        $this->conversationUser = $participant;

        return $this;
    }

    public function forUser($user): static
    {
        return $this->forParticipant($user);
    }

    public function continue(string $conversationId, ?object $as = null): static
    {
        $this->conversationId = $conversationId;
        $this->conversationUser = $as ?? $this->conversationUser;

        return $this;
    }

    public function continueLastConversation(object $as): static
    {
        return $this->forParticipant($as);
    }

    public function messages(): iterable
    {
        return [];
    }

    public function currentConversation(): ?string
    {
        return $this->conversationId;
    }

    public function hasConversationParticipant(): bool
    {
        return $this->conversationUser !== null;
    }

    public function conversationParticipant(): ?object
    {
        return $this->conversationUser;
    }
}
