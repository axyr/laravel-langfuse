<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Objects;

use Axyr\Langfuse\Contracts\EndsOnShutdownInterface;

/**
 * Tracks the observations built during one request or job. Nothing reaches
 * Langfuse until an observation ends, so the shutdown hook ends whatever is
 * still open instead of dropping it.
 */
class OpenObservationRegistry
{
    /** @var array<int, EndsOnShutdownInterface> */
    private array $open = [];

    /** @var array<int, EndsOnShutdownInterface> */
    private array $all = [];

    public function register(EndsOnShutdownInterface $observation): void
    {
        $key = spl_object_id($observation);

        $this->open[$key] = $observation;
        $this->all[$key] = $observation;
    }

    public function unregister(EndsOnShutdownInterface $observation): void
    {
        unset($this->open[spl_object_id($observation)]);
    }

    /**
     * Ends children before their traces, so a root observation still covers the
     * work nested inside it.
     */
    public function endAll(): void
    {
        foreach (array_reverse($this->open, true) as $observation) {
            $observation->endOnShutdown();
        }

        $this->open = [];
    }

    /**
     * @return array<int, EndsOnShutdownInterface>
     */
    public function open(): array
    {
        return array_values($this->open);
    }

    /**
     * Everything registered since the last reset, ended or not. Used by the
     * testing fake to assert on observations that were created but never ended.
     *
     * @return array<int, EndsOnShutdownInterface>
     */
    public function all(): array
    {
        return array_values($this->all);
    }

    public function count(): int
    {
        return count($this->open);
    }

    /**
     * Forgets everything without ending it. `LangfuseClient::shutdown()` calls this
     * after the flush, so a long-running process does not accumulate references.
     */
    public function reset(): void
    {
        $this->open = [];
        $this->all = [];
    }
}
