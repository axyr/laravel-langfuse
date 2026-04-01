<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Prism;

use Axyr\Langfuse\Contracts\LangfuseClientInterface;
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Dto\Usage;
use Axyr\Langfuse\Enums\ObservationLevel;
use Generator;
use Illuminate\Http\Client\RequestException;
use Prism\Prism\Audio\AudioResponse as TextToSpeechResponse;
use Prism\Prism\Audio\SpeechToTextRequest;
use Prism\Prism\Audio\TextResponse as SpeechToTextResponse;
use Prism\Prism\Audio\TextToSpeechRequest;
use Prism\Prism\Embeddings\Request as EmbeddingsRequest;
use Prism\Prism\Embeddings\Response as EmbeddingsResponse;
use Prism\Prism\Images\Request as ImagesRequest;
use Prism\Prism\Images\Response as ImagesResponse;
use Prism\Prism\Moderation\Request as ModerationRequest;
use Prism\Prism\Moderation\Response as ModerationResponse;
use Prism\Prism\Providers\Provider;
use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Structured\Request as StructuredRequest;
use Prism\Prism\Structured\Response as StructuredResponse;
use Prism\Prism\Text\Request as TextRequest;
use Prism\Prism\Text\Response as TextResponse;

class TracingProvider extends Provider
{
    public function __construct(
        private readonly Provider $inner,
        private readonly LangfuseClientInterface $langfuse,
    ) {}

    public function text(TextRequest $request): TextResponse
    {
        $startTime = microtime(true);

        try {
            $response = $this->inner->text($request);

            $this->recordGeneration(
                request: $request,
                output: $response->text,
                usage: $response->usage,
                finishReason: $response->finishReason->name,
                startTime: $startTime,
            );

            return $response;
        } catch (\Throwable $e) {
            $this->recordGenerationError($request, $e, $startTime);

            throw $e;
        }
    }

    public function structured(StructuredRequest $request): StructuredResponse
    {
        $startTime = microtime(true);

        try {
            $response = $this->inner->structured($request);

            $this->recordGeneration(
                request: $request,
                output: $response->structured,
                usage: $response->usage,
                finishReason: $response->finishReason->name,
                startTime: $startTime,
            );

            return $response;
        } catch (\Throwable $e) {
            $this->recordGenerationError($request, $e, $startTime);

            throw $e;
        }
    }

    /**
     * @return Generator<\Prism\Prism\Streaming\Events\StreamEvent>
     */
    public function stream(TextRequest $request): Generator
    {
        $startTime = microtime(true);

        try {
            yield from $this->traceStream($request, $startTime);
        } catch (\Throwable $e) {
            $this->recordGenerationError($request, $e, $startTime);

            throw $e;
        }
    }

    public function embeddings(EmbeddingsRequest $request): EmbeddingsResponse
    {
        return $this->inner->embeddings($request);
    }

    public function images(ImagesRequest $request): ImagesResponse
    {
        return $this->inner->images($request);
    }

    public function moderation(ModerationRequest $request): ModerationResponse
    {
        return $this->inner->moderation($request);
    }

    public function textToSpeech(TextToSpeechRequest $request): TextToSpeechResponse
    {
        return $this->inner->textToSpeech($request);
    }

    public function speechToText(SpeechToTextRequest $request): SpeechToTextResponse
    {
        return $this->inner->speechToText($request);
    }

    public function handleRequestException(string $model, RequestException $e): never
    {
        $this->inner->handleRequestException($model, $e);
    }

    /**
     * @return Generator<\Prism\Prism\Streaming\Events\StreamEvent>
     */
    private function traceStream(TextRequest $request, float $startTime): Generator
    {
        $text = '';
        $streamUsage = null;
        $finishReason = null;

        foreach ($this->inner->stream($request) as $event) {
            if ($event instanceof TextDeltaEvent) {
                $text .= $event->delta;
            }

            if ($event instanceof StreamEndEvent) {
                $streamUsage = $event->usage;
                $finishReason = $event->finishReason->name;
            }

            yield $event;
        }

        $this->recordGeneration(
            request: $request,
            output: $text,
            usage: $streamUsage,
            finishReason: $finishReason,
            startTime: $startTime,
        );
    }

    private function recordGeneration(
        TextRequest|StructuredRequest $request,
        mixed $output,
        ?\Prism\Prism\ValueObjects\Usage $usage,
        ?string $finishReason,
        float $startTime,
    ): void {
        $endTime = microtime(true);

        $trace = $this->createTrace($request);

        $generation = $trace->generation(new GenerationBody(
            name: $request->model(),
            model: $request->model(),
            input: $this->extractInput($request),
            startTime: $this->formatTime($startTime),
            modelParameters: $this->extractModelParameters($request),
        ));

        $generation->end(
            endTime: $this->formatTime($endTime),
            output: $output,
            usage: $this->mapUsage($usage),
            statusMessage: $finishReason,
        );
    }

    private function recordGenerationError(
        TextRequest|StructuredRequest $request,
        \Throwable $e,
        float $startTime,
    ): void {
        $endTime = microtime(true);

        $trace = $this->createTrace($request, ['error' => $e->getMessage()]);

        $generation = $trace->generation(new GenerationBody(
            name: $request->model(),
            model: $request->model(),
            input: $this->extractInput($request),
            startTime: $this->formatTime($startTime),
            modelParameters: $this->extractModelParameters($request),
        ));

        $generation->end(
            endTime: $this->formatTime($endTime),
            statusMessage: $e->getMessage(),
            level: ObservationLevel::ERROR,
        );
    }

    /**
     * @param array<string, mixed> $extraMetadata
     */
    private function createTrace(
        TextRequest|StructuredRequest $request,
        array $extraMetadata = [],
    ): \Axyr\Langfuse\Objects\LangfuseTrace {
        $existing = $this->langfuse->currentTrace();

        if ($existing !== null) {
            return $existing;
        }

        $trace = $this->langfuse->trace(new TraceBody(
            name: 'prism-' . $request->model(),
            input: $this->extractInput($request),
            metadata: [
                'provider' => $request->provider(),
                'source' => 'prism-auto-instrumentation',
                ...$extraMetadata,
            ],
        ));

        $this->langfuse->setCurrentTrace($trace);

        return $trace;
    }

    private function mapUsage(?\Prism\Prism\ValueObjects\Usage $usage): ?Usage
    {
        if ($usage === null) {
            return null;
        }

        return new Usage(
            input: $usage->promptTokens,
            output: $usage->completionTokens,
            total: $usage->promptTokens + $usage->completionTokens,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function extractInput(TextRequest|StructuredRequest $request): array
    {
        $input = [];

        $systemPrompts = $request->systemPrompts();
        if ($systemPrompts !== []) {
            $input['systemPrompts'] = array_map(
                fn($sp): string => $sp->content,
                $systemPrompts,
            );
        }

        if ($request->prompt() !== null) {
            $input['prompt'] = $request->prompt();
        }

        $messages = $request->messages();
        if ($messages !== []) {
            $input['messageCount'] = count($messages);
        }

        return $input;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractModelParameters(TextRequest|StructuredRequest $request): array
    {
        return array_filter([
            'temperature' => $request->temperature(),
            'maxTokens' => $request->maxTokens(),
            'topP' => $request->topP(),
        ], fn(mixed $v): bool => $v !== null);
    }

    private function formatTime(float $microtime): string
    {
        $dt = \DateTimeImmutable::createFromFormat('U.u', sprintf('%.6f', $microtime));

        if ($dt === false) {
            return now()->toIso8601ZuluString();
        }

        return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
    }
}
