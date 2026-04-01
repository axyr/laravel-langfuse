<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Testing;

use Axyr\Langfuse\Contracts\LangfuseClientInterface;
use Axyr\Langfuse\Contracts\PromptInterface;
use Axyr\Langfuse\Dto\CreatePromptBody;
use Axyr\Langfuse\Dto\IdGenerator;
use Axyr\Langfuse\Dto\IngestionEvent;
use Axyr\Langfuse\Dto\PromptListResponse;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Objects\LangfuseTrace;
use PHPUnit\Framework\Assert;

class LangfuseFake implements LangfuseClientInterface
{
    private readonly RecordingEventBatcher $batcher;

    private ?LangfuseTrace $currentTrace = null;

    /** @var array<PromptInterface> */
    private array $promptResponses = [];

    /** @var array<string> */
    private array $deletedScores = [];

    /** @var array<CreatePromptBody> */
    private array $createdPrompts = [];

    public function __construct()
    {
        $this->batcher = new RecordingEventBatcher();
    }

    public function trace(TraceBody $body): LangfuseTrace
    {
        return new LangfuseTrace(
            body: $body,
            batcher: $this->batcher,
        );
    }

    public function currentTrace(): ?LangfuseTrace
    {
        return $this->currentTrace;
    }

    public function setCurrentTrace(LangfuseTrace $trace): void
    {
        $this->currentTrace = $trace;
    }

    public function score(ScoreBody $body): void
    {
        $event = new IngestionEvent(
            id: $body->id,
            type: \Axyr\Langfuse\Enums\EventType::ScoreCreate,
            timestamp: IdGenerator::timestamp(),
            body: $body,
        );

        $this->batcher->enqueue($event);
    }

    public function deleteScore(string $scoreId): bool
    {
        $this->deletedScores[] = $scoreId;

        return true;
    }

    public function flush(): void
    {
        $this->batcher->flush();
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function prompt(
        string $name,
        ?int $version = null,
        ?string $label = null,
        string|array|null $fallback = null,
    ): PromptInterface {
        if (isset($this->promptResponses[$name])) {
            return $this->promptResponses[$name];
        }

        if (is_string($fallback)) {
            return \Axyr\Langfuse\Dto\PromptFactory::fallbackText($name, $fallback);
        }

        if (is_array($fallback)) {
            return \Axyr\Langfuse\Dto\PromptFactory::fallbackChat($name, $fallback);
        }

        throw \Axyr\Langfuse\Exceptions\PromptNotFoundException::forName($name);
    }

    public function createPrompt(CreatePromptBody $body): ?PromptInterface
    {
        $this->createdPrompts[] = $body;

        return \Axyr\Langfuse\Dto\PromptFactory::fromApiResponse($body->toArray());
    }

    public function listPrompts(?string $name = null, ?string $label = null, ?int $page = null, ?int $limit = null): ?PromptListResponse
    {
        return PromptListResponse::fromArray([
            'data' => [],
            'meta' => ['totalItems' => 0, 'totalPages' => 0, 'page' => $page ?? 1, 'limit' => $limit ?? 10],
        ]);
    }

    public function withPrompt(PromptInterface $prompt): self
    {
        $this->promptResponses[$prompt->getName()] = $prompt;

        return $this;
    }

    /**
     * @return array<IngestionEvent>
     */
    public function events(): array
    {
        return $this->batcher->events();
    }

    public function assertTraceCreated(?string $name = null): self
    {
        $traces = $this->batcher->eventsOfType('trace-create');

        Assert::assertNotEmpty($traces, 'Expected at least one trace to be created, but none were.');

        if ($name !== null) {
            $names = array_map(fn(IngestionEvent $e): mixed => $e->body->toArray()['name'] ?? null, $traces);
            Assert::assertContains($name, $names, "Expected a trace named '{$name}' but none was found.");
        }

        return $this;
    }

    public function assertGenerationCreated(?string $name = null): self
    {
        $generations = $this->batcher->eventsOfType('generation-create');

        Assert::assertNotEmpty($generations, 'Expected at least one generation to be created, but none were.');

        if ($name !== null) {
            $names = array_map(fn(IngestionEvent $e): mixed => $e->body->toArray()['name'] ?? null, $generations);
            Assert::assertContains($name, $names, "Expected a generation named '{$name}' but none was found.");
        }

        return $this;
    }

    public function assertScoreCreated(?string $name = null): self
    {
        $scores = $this->batcher->eventsOfType('score-create');

        Assert::assertNotEmpty($scores, 'Expected at least one score to be created, but none were.');

        if ($name !== null) {
            $names = array_map(fn(IngestionEvent $e): mixed => $e->body->toArray()['name'] ?? null, $scores);
            Assert::assertContains($name, $names, "Expected a score named '{$name}' but none was found.");
        }

        return $this;
    }

    public function assertSpanCreated(?string $name = null): self
    {
        $spans = $this->batcher->eventsOfType('span-create');

        Assert::assertNotEmpty($spans, 'Expected at least one span to be created, but none were.');

        if ($name !== null) {
            $names = array_map(fn(IngestionEvent $e): mixed => $e->body->toArray()['name'] ?? null, $spans);
            Assert::assertContains($name, $names, "Expected a span named '{$name}' but none was found.");
        }

        return $this;
    }

    public function assertEventCreated(?string $name = null): self
    {
        $events = $this->batcher->eventsOfType('event-create');

        Assert::assertNotEmpty($events, 'Expected at least one event to be created, but none were.');

        if ($name !== null) {
            $names = array_map(fn(IngestionEvent $e): mixed => $e->body->toArray()['name'] ?? null, $events);
            Assert::assertContains($name, $names, "Expected an event named '{$name}' but none was found.");
        }

        return $this;
    }

    public function assertNothingSent(): self
    {
        Assert::assertEmpty(
            $this->batcher->events(),
            'Expected no events to be sent, but ' . count($this->batcher->events()) . ' were recorded.',
        );

        return $this;
    }

    public function assertEventCount(int $expected): self
    {
        Assert::assertCount(
            $expected,
            $this->batcher->events(),
            'Expected ' . $expected . ' events but found ' . count($this->batcher->events()) . '.',
        );

        return $this;
    }

    public function assertScoreDeleted(?string $scoreId = null): self
    {
        Assert::assertNotEmpty($this->deletedScores, 'Expected at least one score to be deleted, but none were.');

        if ($scoreId !== null) {
            Assert::assertContains($scoreId, $this->deletedScores, "Expected score '{$scoreId}' to be deleted but it was not.");
        }

        return $this;
    }

    public function assertPromptCreated(?string $name = null): self
    {
        Assert::assertNotEmpty($this->createdPrompts, 'Expected at least one prompt to be created, but none were.');

        if ($name !== null) {
            $names = array_map(fn(CreatePromptBody $p): string => $p->name, $this->createdPrompts);
            Assert::assertContains($name, $names, "Expected a prompt named '{$name}' to be created but it was not.");
        }

        return $this;
    }
}
