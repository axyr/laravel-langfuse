<?php

declare(strict_types=1);

namespace Axyr\Langfuse\LaravelAi;

use Axyr\Langfuse\Tracing\TraceUserId;

/**
 * The Langfuse sessionId and userId an agent run belongs to, taken from
 * Laravel AI's own conversation state: the conversation id becomes the
 * session and the conversation participant becomes the user.
 *
 * Agents are inspected by method rather than by type, so both agents using
 * the RemembersConversations trait and agents implementing the contract work.
 */
final readonly class ConversationContext
{
    public function __construct(
        public ?string $sessionId = null,
        public ?string $userId = null,
    ) {}

    public static function fromAgent(object $agent): self
    {
        return new self(
            sessionId: self::stringOrNull(self::call($agent, 'currentConversation')),
            userId: TraceUserId::from(self::call($agent, 'conversationParticipant')),
        );
    }

    /**
     * After a run completes, Laravel AI stamps the conversation onto the response,
     * which is the first place a brand new conversation's id shows up.
     */
    public static function fromResponse(object $response, object $agent): self
    {
        $fromAgent = self::fromAgent($agent);

        return new self(
            sessionId: self::stringOrNull($response->conversationId ?? null) ?? $fromAgent->sessionId,
            userId: TraceUserId::from($response->conversationUser ?? null) ?? $fromAgent->userId,
        );
    }

    public function only(bool $session, bool $user): self
    {
        return new self(
            sessionId: $session ? $this->sessionId : null,
            userId: $user ? $this->userId : null,
        );
    }

    public function isEmpty(): bool
    {
        return $this->sessionId === null && $this->userId === null;
    }

    private static function call(object $object, string $method): mixed
    {
        return method_exists($object, $method) ? $object->{$method}() : null;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
