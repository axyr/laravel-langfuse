<?php

declare(strict_types=1);

namespace Laravel\Ai\Contracts;

interface RemembersConversations extends Conversational
{
    public function forParticipant(object $participant): static;

    public function forUser(object $user): static;

    public function continue(string $conversationId, ?object $as = null): static;

    public function continueLastConversation(object $as): static;

    public function currentConversation(): ?string;

    public function hasConversationParticipant(): bool;

    public function conversationParticipant(): ?object;
}
