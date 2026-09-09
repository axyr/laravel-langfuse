<?php

declare(strict_types=1);

use Axyr\Langfuse\LaravelAi\ConversationContext;
use Illuminate\Auth\GenericUser;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;

function makeContextAgent(): Agent
{
    return new class () implements Agent {
        use RemembersConversations;
    };
}

it('is empty for agents without conversation state', function () {
    $context = ConversationContext::fromAgent(makeContextAgent());

    expect($context->isEmpty())->toBeTrue()
        ->and(ConversationContext::fromAgent(new stdClass())->isEmpty())->toBeTrue();
});

it('reads the conversation id and participant from the agent', function () {
    $agent = makeContextAgent()->continue('conversation-42', new GenericUser(['id' => 7]));

    $context = ConversationContext::fromAgent($agent);

    expect($context->sessionId)->toBe('conversation-42')
        ->and($context->userId)->toBe('7');
});

it('prefers the conversation stamped on the response over the agent state', function () {
    $agent = makeContextAgent()->continue('stale', new GenericUser(['id' => 1]));
    $response = (object) ['conversationId' => 'fresh', 'conversationUser' => new GenericUser(['id' => 2])];

    $context = ConversationContext::fromResponse($response, $agent);

    expect($context->sessionId)->toBe('fresh')
        ->and($context->userId)->toBe('2');
});

it('falls back to the agent state when the response carries no conversation', function () {
    $agent = makeContextAgent()->continue('conversation-42', new GenericUser(['id' => 7]));

    $context = ConversationContext::fromResponse(new stdClass(), $agent);

    expect($context->sessionId)->toBe('conversation-42')
        ->and($context->userId)->toBe('7');
});

it('can drop either identifier', function () {
    $context = new ConversationContext(sessionId: 's', userId: 'u');

    expect($context->only(session: false, user: true)->sessionId)->toBeNull()
        ->and($context->only(session: false, user: true)->userId)->toBe('u')
        ->and($context->only(session: true, user: false)->userId)->toBeNull()
        ->and($context->only(session: false, user: false)->isEmpty())->toBeTrue();
});
