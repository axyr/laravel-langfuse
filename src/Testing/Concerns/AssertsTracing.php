<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Testing\Concerns;

use Axyr\Langfuse\Contracts\EndsOnShutdownInterface;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Enums\ObservationType;
use Axyr\Langfuse\Objects\LangfuseGeneration;
use Axyr\Langfuse\Objects\LangfuseSpan;
use Axyr\Langfuse\Objects\LangfuseTrace;
use Axyr\Langfuse\Objects\OpenObservationRegistry;
use Axyr\Langfuse\Testing\RecordingEventBatcher;
use Axyr\Langfuse\Tracing\CompletedObservation;
use PHPUnit\Framework\Assert;

/**
 * Assertions on the v4 lifecycle: an object is "created" as soon as it exists,
 * and "ended" once it has been handed to the batcher for export.
 */
trait AssertsTracing
{
    /**
     * @return array<int, LangfuseTrace>
     */
    public function traces(): array
    {
        /** @var array<int, LangfuseTrace> $traces */
        $traces = $this->objectsOf(LangfuseTrace::class);

        return $traces;
    }

    /**
     * @return array<int, LangfuseSpan>
     */
    public function spans(): array
    {
        /** @var array<int, LangfuseSpan> $spans */
        $spans = $this->objectsOf(LangfuseSpan::class);

        return $spans;
    }

    /**
     * @return array<int, LangfuseGeneration>
     */
    public function generations(): array
    {
        /** @var array<int, LangfuseGeneration> $generations */
        $generations = $this->objectsOf(LangfuseGeneration::class);

        return $generations;
    }

    /**
     * Everything a flush would export.
     *
     * @return array<int, CompletedObservation>
     */
    public function observations(): array
    {
        return $this->recorder()->observations();
    }

    /**
     * @return array<int, ScoreBody>
     */
    public function scores(): array
    {
        return $this->recorder()->scores();
    }

    public function assertTraceCreated(?string $name = null): self
    {
        return $this->assertCreated($this->traces(), $name, 'trace');
    }

    public function assertSpanCreated(?string $name = null): self
    {
        return $this->assertCreated($this->spans(), $name, 'span');
    }

    public function assertGenerationCreated(?string $name = null): self
    {
        return $this->assertCreated($this->generations(), $name, 'generation');
    }

    public function assertTraceEnded(?string $name = null): self
    {
        return $this->assertCreated($this->endedOnly($this->traces()), $name, 'ended trace');
    }

    public function assertSpanEnded(?string $name = null): self
    {
        return $this->assertCreated($this->endedOnly($this->spans()), $name, 'ended span');
    }

    public function assertGenerationEnded(?string $name = null): self
    {
        return $this->assertCreated($this->endedOnly($this->generations()), $name, 'ended generation');
    }

    public function assertEventCreated(?string $name = null): self
    {
        $events = $this->recorder()->observationsOfType(ObservationType::Event);

        Assert::assertNotEmpty($events, 'Expected at least one event to be created, but none were.');

        if ($name !== null) {
            $names = array_map(fn(CompletedObservation $o): string => $o->name(), $events);
            Assert::assertContains($name, $names, "Expected an event named '{$name}' but none was found.");
        }

        return $this;
    }

    public function assertScoreCreated(?string $name = null): self
    {
        $scores = $this->recorder()->scores();

        Assert::assertNotEmpty($scores, 'Expected at least one score to be created, but none were.');

        if ($name !== null) {
            $names = array_map(fn(ScoreBody $score): string => $score->name, $scores);
            Assert::assertContains($name, $names, "Expected a score named '{$name}' but none was found.");
        }

        return $this;
    }

    public function assertNothingSent(): self
    {
        Assert::assertEmpty(
            $this->registry()->all(),
            'Expected no observations to be created, but ' . count($this->registry()->all()) . ' were.',
        );

        Assert::assertSame(
            0,
            $this->recorder()->count(),
            'Expected nothing to be recorded, but ' . $this->recorder()->count() . ' items were.',
        );

        return $this;
    }

    /**
     * Counts what a flush would send: exported observations and events plus
     * queued scores. Unlike v3 there is no create/update double counting, and an
     * observation that never ended is not counted.
     */
    public function assertEventCount(int $expected): self
    {
        Assert::assertSame(
            $expected,
            $this->recorder()->count(),
            'Expected ' . $expected . ' recorded items but found ' . $this->recorder()->count() . '.',
        );

        return $this;
    }

    /**
     * @param  callable(CompletedObservation): bool  $predicate
     */
    public function assertObservationHas(string $name, callable $predicate): self
    {
        $matching = array_filter(
            $this->recorder()->observations(),
            fn(CompletedObservation $o): bool => $o->name() === $name,
        );

        Assert::assertNotEmpty($matching, "Expected an exported observation named '{$name}' but none was found.");
        Assert::assertNotEmpty(
            array_filter($matching, $predicate),
            "Expected an observation named '{$name}' matching the given predicate but none did.",
        );

        return $this;
    }

    /**
     * @param  array<int, LangfuseTrace|LangfuseSpan|LangfuseGeneration>  $objects
     */
    private function assertCreated(array $objects, ?string $name, string $label): self
    {
        Assert::assertNotEmpty($objects, "Expected at least one {$label}, but none were found.");

        if ($name !== null) {
            $names = array_map(
                fn(LangfuseTrace|LangfuseSpan|LangfuseGeneration $object): ?string => $object->getBody()->name,
                $objects,
            );
            Assert::assertContains($name, $names, "Expected a {$label} named '{$name}' but none was found.");
        }

        return $this;
    }

    /**
     * @template T of EndsOnShutdownInterface
     *
     * @param  array<int, T>  $objects
     * @return array<int, T>
     */
    private function endedOnly(array $objects): array
    {
        return array_values(array_filter(
            $objects,
            fn(EndsOnShutdownInterface $object): bool => $object->hasEnded(),
        ));
    }

    /**
     * @param  class-string  $class
     * @return array<int, EndsOnShutdownInterface>
     */
    private function objectsOf(string $class): array
    {
        return array_values(array_filter(
            $this->registry()->all(),
            fn(EndsOnShutdownInterface $object): bool => $object::class === $class,
        ));
    }

    abstract public function registry(): OpenObservationRegistry;

    abstract public function recorder(): RecordingEventBatcher;
}
