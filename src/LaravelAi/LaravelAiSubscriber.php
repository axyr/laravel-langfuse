<?php

declare(strict_types=1);

namespace Axyr\Langfuse\LaravelAi;

use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\LangfuseClientInterface;
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Dto\Usage;
use Axyr\Langfuse\Objects\LangfuseSpan;
use Axyr\Langfuse\Objects\LangfuseTrace;
use Axyr\Langfuse\Objects\NullLangfuseTrace;
use DateTimeImmutable;
use DateTimeZone;
use Laravel\Ai\Contracts\RemembersConversations;
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

    /** @var array<string, LangfuseSpan> */
    private array $toolSpans = [];

    /** @var array<string, float> */
    private array $toolStartTimes = [];

    public function __construct(
        private readonly LangfuseClientInterface $langfuse,
        private readonly LangfuseConfig $config,
    ) {}

    public function handlePromptingAgent(PromptingAgent $event): void
    {
        $this->startTimes[$event->invocationId] = microtime(true);
        $this->getOrCreateTrace($event);
    }

    public function handleAgentPrompted(AgentPrompted $event): void
    {
        $startTime = $this->startTimes[$event->invocationId] ?? microtime(true);
        $endTime = microtime(true);

        $trace = $this->getOrCreateTrace($event);
        $response = $event->response;

        $model = $response->meta->model ?? $event->prompt->model;

        $generation = $trace->generation(new GenerationBody(
            name: $model,
            model: $model,
            input: $event->prompt->prompt,
            startTime: $this->formatTime($startTime),
        ));

        $generation->end(
            endTime: $this->formatTime($endTime),
            output: $response->text,
            usage: $this->mapUsage($response->usage),
        );

        unset($this->startTimes[$event->invocationId]);
    }

    public function handleInvokingTool(InvokingTool $event): void
    {
        $this->toolStartTimes[$event->toolInvocationId] = microtime(true);

        $trace = $this->getOrCreateTraceFromTool($event);
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

    private function getOrCreateTrace(PromptingAgent|AgentPrompted $event): LangfuseTrace
    {
        $agent = $event->prompt->agent;

        return $this->resolveTrace($event->invocationId, new TraceBody(
            name: 'laravel-ai-' . $this->getShortClassName($agent),
            input: $event->prompt->prompt,
            sessionId: $this->resolveSessionId($agent),
            userId: $this->resolveUserId($agent),
            metadata: [
                'model' => $event->prompt->model,
                'source' => 'laravel-ai-auto-instrumentation',
            ],
        ));
    }

    private function getOrCreateTraceFromTool(InvokingTool $event): LangfuseTrace
    {
        $agent = $event->agent;

        return $this->resolveTrace($event->invocationId, new TraceBody(
            name: 'laravel-ai-' . $this->getShortClassName($agent),
            sessionId: $this->resolveSessionId($agent),
            userId: $this->resolveUserId($agent),
            metadata: [
                'source' => 'laravel-ai-auto-instrumentation',
            ],
        ));
    }

    private function resolveSessionId(object $agent): ?string
    {
        if (! $this->config->laravelAiSessionTracingEnabled || ! $agent instanceof RemembersConversations) {
            return null;
        }

        return $agent->currentConversation();
    }

    private function resolveUserId(object $agent): ?string
    {
        if (! $this->config->laravelAiUserTracingEnabled || ! $agent instanceof RemembersConversations) {
            return null;
        }

        $participant = $agent->conversationParticipant();

        if ($participant === null) {
            return null;
        }

        $id = get_object_vars($participant)['id'] ?? null;

        return match (true) {
            is_string($id) => $id,
            is_int($id) => (string) $id,
            default => null,
        };
    }

    private function resolveTrace(string $invocationId, TraceBody $body): LangfuseTrace
    {
        if (isset($this->traces[$invocationId])) {
            return $this->traces[$invocationId];
        }

        $existing = $this->langfuse->currentTrace();

        if (! $existing instanceof NullLangfuseTrace) {
            $this->traces[$invocationId] = $existing;

            return $existing;
        }

        $trace = $this->langfuse->trace($body);
        $this->langfuse->setCurrentTrace($trace);
        $this->traces[$invocationId] = $trace;

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
