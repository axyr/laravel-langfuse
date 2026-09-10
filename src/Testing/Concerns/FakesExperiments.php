<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Testing\Concerns;

use Axyr\Langfuse\Dto\CursorMeta;
use Axyr\Langfuse\Dto\ExperimentItemListResponse;
use Axyr\Langfuse\Dto\ExperimentItemQuery;
use Axyr\Langfuse\Dto\ExperimentItemResponse;
use Axyr\Langfuse\Dto\ExperimentListResponse;
use Axyr\Langfuse\Dto\ExperimentQuery;
use Axyr\Langfuse\Dto\ExperimentResponse;
use Axyr\Langfuse\Objects\LangfuseTrace;
use PHPUnit\Framework\Assert;

/**
 * Experiments replace dataset runs. Writing an experiment item is tracing, so
 * the assertion reads the experiment context off the traces that were created.
 *
 * The signatures mirror LangfuseClientInterface, so filter arguments a seeded
 * fake ignores are kept.
 *
 * @SuppressWarnings("PHPMD.UnusedFormalParameter")
 */
trait FakesExperiments
{
    /** @var array<string, ExperimentResponse> */
    private array $experimentsById = [];

    /** @var array<int, ExperimentItemResponse> */
    private array $experimentItems = [];

    public function listExperiments(ExperimentQuery $query): ?ExperimentListResponse
    {
        return new ExperimentListResponse(array_values($this->experimentsById), new CursorMeta(limit: $query->limit));
    }

    public function listExperimentItems(ExperimentItemQuery $query): ?ExperimentItemListResponse
    {
        return new ExperimentItemListResponse($this->experimentItems, new CursorMeta(limit: $query->limit));
    }

    public function getExperiment(string $experimentId, string $fromStartTime, ?string $toStartTime = null): ?ExperimentResponse
    {
        return $this->experimentsById[$experimentId] ?? null;
    }

    public function withExperiment(ExperimentResponse $experiment): self
    {
        $this->experimentsById[$experiment->id] = $experiment;

        return $this;
    }

    public function withExperimentItem(ExperimentItemResponse $item): self
    {
        $this->experimentItems[] = $item;

        return $this;
    }

    public function assertExperimentItemTraced(?string $experimentName = null): self
    {
        $traced = array_filter(
            $this->traces(),
            fn(LangfuseTrace $trace): bool => $trace->getBody()->experimentItem !== null,
        );

        Assert::assertNotEmpty($traced, 'Expected at least one experiment item trace, but none were created.');

        if ($experimentName !== null) {
            $names = array_map(
                fn(LangfuseTrace $trace): ?string => $trace->getBody()->experiment?->name,
                $traced,
            );
            Assert::assertContains($experimentName, $names, "Expected an experiment item trace for '{$experimentName}' but none was found.");
        }

        return $this;
    }

    /**
     * @return array<int, LangfuseTrace>
     */
    abstract public function traces(): array;
}
