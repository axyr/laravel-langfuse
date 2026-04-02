<?php

declare(strict_types=1);

namespace Axyr\Langfuse\NeuronAi;

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
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Observability\Events\AgentError;
use NeuronAI\Observability\Events\InferenceStop;
use NeuronAI\Observability\Events\Retrieved;
use NeuronAI\Observability\Events\Retrieving;
use NeuronAI\Observability\Events\ToolCalled;
use NeuronAI\Observability\Events\ToolCalling;
use NeuronAI\Observability\Events\WorkflowEnd;
use NeuronAI\Observability\ObserverInterface;

class NeuronAiObserver implements ObserverInterface
{
    /** @var array<string, string> */
    protected array $sourceHandlers = [
        'workflow-end' => 'workflowEnd',
        'inference-stop' => 'inferenceStop',
        'tool-calling' => 'toolCalling',
        'rag-retrieving' => 'ragRetrieving',
        'error' => 'handleError',
    ];

    /** @var array<string, string> */
    protected array $dataHandlers = [
        'tool-called' => 'toolCalled',
        'rag-retrieved' => 'ragRetrieved',
    ];

    private ?LangfuseTrace $trace = null;

    /** @var array<string, float> */
    private array $startTimes = [];

    /** @var array<string, LangfuseSpan> */
    private array $spans = [];

    public function __construct(
        private readonly LangfuseClientInterface $langfuse,
    ) {}

    public static function make(): self
    {
        return app(self::class);
    }

    public function onEvent(string $event, object $source, mixed $data = null): void
    {
        if ($this->handleSimpleEvent($event, $source)) {
            return;
        }

        if (isset($this->dataHandlers[$event])) {
            $this->{$this->dataHandlers[$event]}($data);

            return;
        }

        if (isset($this->sourceHandlers[$event])) {
            $this->{$this->sourceHandlers[$event]}($source, $data);
        }
    }

    protected function workflowEnd(object $source, ?WorkflowEnd $data): void
    {
        $trace = $this->getOrCreateTrace($source);

        if ($data !== null) {
            $trace->update(new TraceBody(
                output: $data->state->all(),
            ));
        }

        $this->langfuse->flush();
    }

    protected function inferenceStop(object $source, ?InferenceStop $data): void
    {
        $startTime = $this->startTimes['inference'] ?? microtime(true);
        $trace = $this->getOrCreateTrace($source);

        $generation = $trace->generation(new GenerationBody(
            name: 'inference',
            input: $this->extractInferenceInput($data),
            startTime: $this->formatTime($startTime),
        ));

        $generation->end(
            endTime: $this->formatTime(microtime(true)),
            output: $data?->response->getContent(),
            usage: $this->extractInferenceUsage($data),
        );

        unset($this->startTimes['inference']);
    }

    protected function toolCalling(object $source, ?ToolCalling $data): void
    {
        if ($data === null) {
            return;
        }

        $toolName = $data->tool->getName();
        $this->startTimes["tool-{$toolName}"] = microtime(true);

        $trace = $this->getOrCreateTrace($source);

        $span = $trace->span(new SpanBody(
            name: "tool-{$toolName}",
            startTime: $this->formatTime($this->startTimes["tool-{$toolName}"]),
        ));

        $this->spans["tool-{$toolName}"] = $span;
    }

    protected function toolCalled(?ToolCalled $data): void
    {
        if ($data === null) {
            return;
        }

        $toolName = $data->tool->getName();
        $span = $this->spans["tool-{$toolName}"] ?? null;

        if ($span === null) {
            return;
        }

        $result = null;

        try {
            $result = $data->tool->getResult();
        } catch (\Throwable) {
        }

        $span->end(
            endTime: $this->formatTime(microtime(true)),
            output: $result,
        );

        unset($this->spans["tool-{$toolName}"], $this->startTimes["tool-{$toolName}"]);
    }

    protected function ragRetrieving(object $source, ?Retrieving $data): void
    {
        if ($data === null) {
            return;
        }

        $this->startTimes['rag'] = microtime(true);

        $trace = $this->getOrCreateTrace($source);

        $span = $trace->span(new SpanBody(
            name: 'rag-retrieval',
            startTime: $this->formatTime($this->startTimes['rag']),
            input: $data->question->getContent(),
        ));

        $this->spans['rag'] = $span;
    }

    protected function ragRetrieved(?Retrieved $data): void
    {
        $span = $this->spans['rag'] ?? null;

        if ($span === null) {
            return;
        }

        $output = null;

        if ($data !== null) {
            $output = [
                'question' => $data->question->getContent(),
                'documents' => count($data->documents),
            ];
        }

        $span->end(
            endTime: $this->formatTime(microtime(true)),
            output: $output,
        );

        unset($this->spans['rag'], $this->startTimes['rag']);
    }

    protected function handleError(object $source, ?AgentError $data): void
    {
        if ($data === null) {
            return;
        }

        $trace = $this->getOrCreateTrace($source);

        $trace->update(new TraceBody(
            metadata: [
                'error' => $data->exception->getMessage(),
                'error_trace' => $data->exception->getTraceAsString(),
            ],
        ));
    }

    private function handleSimpleEvent(string $event, object $source): bool
    {
        if ($event === 'workflow-start') {
            $this->getOrCreateTrace($source);

            return true;
        }

        if ($event === 'inference-start') {
            $this->startTimes['inference'] = microtime(true);

            return true;
        }

        return false;
    }

    private function extractInferenceInput(?InferenceStop $data): mixed
    {
        if ($data !== null && $data->message instanceof Message) {
            return $data->message->getContent();
        }

        return null;
    }

    private function extractInferenceUsage(?InferenceStop $data): ?Usage
    {
        $neuronUsage = $data?->response->getUsage();

        if ($neuronUsage === null) {
            return null;
        }

        return new Usage(
            input: $neuronUsage->inputTokens,
            output: $neuronUsage->outputTokens,
            total: $neuronUsage->getTotal(),
        );
    }

    private function getOrCreateTrace(object $source): LangfuseTrace
    {
        if ($this->trace !== null) {
            return $this->trace;
        }

        $existing = $this->langfuse->currentTrace();

        if (! $existing instanceof NullLangfuseTrace) {
            $this->trace = $existing;

            return $existing;
        }

        $agentName = $this->getShortClassName($source);

        $trace = $this->langfuse->trace(new TraceBody(
            name: "neuron-ai-{$agentName}",
            metadata: [
                'source' => 'neuron-ai-auto-instrumentation',
            ],
        ));

        $this->langfuse->setCurrentTrace($trace);
        $this->trace = $trace;

        return $trace;
    }

    private function getShortClassName(object $object): string
    {
        $className = get_class($object);
        $parts = explode('\\', $className);

        return end($parts);
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
