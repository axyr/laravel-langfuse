<?php

declare(strict_types=1);

namespace Axyr\Langfuse\LaravelAi;

use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\LangfuseClientInterface;
use Axyr\Langfuse\Contracts\PromptInterface;
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Dto\Usage;
use Axyr\Langfuse\Objects\LangfuseSpan;
use Axyr\Langfuse\Objects\LangfuseTrace;
use Axyr\Langfuse\Objects\NullLangfuseTrace;
use Axyr\Langfuse\Prompt\CurrentPromptRegistry;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\StreamingAgent;
use Laravel\Ai\Events\ToolInvoked;

class LaravelAiSubscriber
{
    /** @var array<string, float> */
    private array $startTimes = [];

    /** @var array<string, LangfuseTrace> */
    private array $traces = [];

    /** @var array<string, true> Invocations whose trace this subscriber created itself. */
    private array $ownedInvocations = [];

    /** @var array<string, LangfuseSpan> */
    private array $toolSpans = [];

    /** @var array<string, float> */
    private array $toolStartTimes = [];

    /** @var array<string, PromptInterface> */
    private array $managedPrompts = [];

    public function __construct(
        private readonly LangfuseClientInterface $langfuse,
        private readonly LangfuseConfig $config,
        private readonly CurrentPromptRegistry $prompts = new CurrentPromptRegistry(),
    ) {}

    public function handlePromptingAgent(PromptingAgent $event): void
    {
        $this->startTimes[$event->invocationId] = microtime(true);

        // Capture the prompt resolved before this invocation right away, so prompts
        // resolved by tools or nested agents during the run cannot replace it.
        $managedPrompt = $this->prompts->consume();

        if ($managedPrompt !== null) {
            $this->managedPrompts[$event->invocationId] = $managedPrompt;
        }

        $this->resolveTrace($event->invocationId, fn(): TraceBody => $this->traceBodyFor($event));
    }

    public function handleAgentPrompted(AgentPrompted $event): void
    {
        $startTime = $this->startTimes[$event->invocationId] ?? microtime(true);
        $endTime = microtime(true);

        $trace = $this->resolveTrace($event->invocationId, fn(): TraceBody => $this->traceBodyFor($event));

        $this->recordGeneration($trace, $event, $startTime, $endTime);
        $this->updateTrace($trace, $event);

        unset($this->startTimes[$event->invocationId], $this->managedPrompts[$event->invocationId]);
    }

    private function recordGeneration(LangfuseTrace $trace, AgentPrompted $event, float $startTime, float $endTime): void
    {
        $response = $event->response;
        $model = $response->meta->model ?? $event->prompt->model;

        $body = new GenerationBody(
            name: $model,
            model: $model,
            input: $event->prompt->prompt,
            startTime: $this->formatTime($startTime),
        );

        $generation = $trace->generation(
            $body->withPrompt($this->managedPrompts[$event->invocationId] ?? null),
        );

        $generation->end(
            endTime: $this->formatTime($endTime),
            output: $response->text,
            usage: $this->mapUsage($response->usage),
        );
    }

    public function handleInvokingTool(InvokingTool $event): void
    {
        $this->toolStartTimes[$event->toolInvocationId] = microtime(true);

        $trace = $this->resolveTrace($event->invocationId, fn(): TraceBody => new TraceBody(
            name: 'laravel-ai-' . $this->getShortClassName($event->agent),
            metadata: ['source' => 'laravel-ai-auto-instrumentation'],
        ));
        $toolName = $this->getShortClassName($event->tool);

        $span = $trace->span(new SpanBody(
            name: "tool-{$toolName}",
            startTime: $this->formatTime($this->toolStartTimes[$event->toolInvocationId]),
            input: $event->arguments,
        ));

        $this->toolSpans[$event->toolInvocationId] = $span;
    }

    public function handleToolInvoked(ToolInvoked $event): void
    {
        $span = $this->toolSpans[$event->toolInvocationId] ?? null;

        if ($span === null) {
            return;
        }

        $span->end(
            endTime: $this->formatTime(microtime(true)),
            output: $event->result,
        );

        unset($this->toolSpans[$event->toolInvocationId], $this->toolStartTimes[$event->toolInvocationId]);
    }

    /**
     * @return array<string, string>
     */
    public function subscribe(): array
    {
        return [
            PromptingAgent::class => 'handlePromptingAgent',
            StreamingAgent::class => 'handlePromptingAgent',
            AgentPrompted::class => 'handleAgentPrompted',
            AgentStreamed::class => 'handleAgentPrompted',
            InvokingTool::class => 'handleInvokingTool',
            ToolInvoked::class => 'handleToolInvoked',
        ];
    }

    private function traceBodyFor(PromptingAgent|AgentPrompted $event): TraceBody
    {
        $context = $this->contextFor($event->prompt->agent);

        return new TraceBody(
            name: 'laravel-ai-' . $this->getShortClassName($event->prompt->agent),
            userId: $context->userId,
            sessionId: $context->sessionId,
            input: $event->prompt->prompt,
            metadata: [
                'model' => $event->prompt->model,
                'source' => 'laravel-ai-auto-instrumentation',
            ],
        );
    }

    /**
     * Completes the trace once the run is done. The response text becomes the
     * trace output only for traces this subscriber created, so a request trace or
     * a manual workflow trace keeps its own output. Session and user ids are sent
     * for every trace, since a new conversation only gets its id after the run.
     */
    private function updateTrace(LangfuseTrace $trace, AgentPrompted $event): void
    {
        $owned = isset($this->ownedInvocations[$event->invocationId]);
        $context = $this->contextFor($event->prompt->agent, $event->response);

        if (! $owned && $context->isEmpty()) {
            return;
        }

        $trace->update(new TraceBody(
            userId: $context->userId,
            sessionId: $context->sessionId,
            output: $owned ? $event->response->text : null,
        ));
    }

    private function contextFor(object $agent, ?object $response = null): ConversationContext
    {
        $context = $response === null
            ? ConversationContext::fromAgent($agent)
            : ConversationContext::fromResponse($response, $agent);

        return $context->only(
            session: $this->config->sessionTracingEnabled,
            user: $this->config->userTracingEnabled,
        );
    }

    /**
     * @param Closure(): TraceBody $body Built only when a trace actually has to be created.
     */
    private function resolveTrace(string $invocationId, Closure $body): LangfuseTrace
    {
        if (isset($this->traces[$invocationId])) {
            return $this->traces[$invocationId];
        }

        $existing = $this->langfuse->currentTrace();

        if (! $existing instanceof NullLangfuseTrace) {
            $this->traces[$invocationId] = $existing;

            return $existing;
        }

        $trace = $this->langfuse->trace($body());
        $this->langfuse->setCurrentTrace($trace);
        $this->traces[$invocationId] = $trace;
        $this->ownedInvocations[$invocationId] = true;

        return $trace;
    }

    private function getShortClassName(object $object): string
    {
        $className = get_class($object);
        $parts = explode('\\', $className);

        return end($parts);
    }

    private function mapUsage(\Laravel\Ai\Responses\Data\Usage $usage): Usage
    {
        return new Usage(
            input: $usage->promptTokens,
            output: $usage->completionTokens,
            total: $usage->promptTokens + $usage->completionTokens,
        );
    }

    private function formatTime(float $microtime): string
    {
        $dt = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6f', $microtime));

        if ($dt === false) {
            return now()->toIso8601ZuluString();
        }

        return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
    }
}
