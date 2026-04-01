<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Testing;

use Axyr\Langfuse\Contracts\EventBatcherInterface;
use Axyr\Langfuse\Dto\IngestionEvent;

class RecordingEventBatcher implements EventBatcherInterface
{
    /** @var array<IngestionEvent> */
    private array $events = [];

    public function enqueue(IngestionEvent $event): void
    {
        $this->events[] = $event;
    }

    public function flush(): void {}

    public function count(): int
    {
        return count($this->events);
    }

    /**
     * @return array<IngestionEvent>
     */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * @return array<IngestionEvent>
     */
    public function eventsOfType(string $type): array
    {
        return array_values(array_filter(
            $this->events,
            fn(IngestionEvent $e): bool => $e->type->value === $type,
        ));
    }

    public function reset(): void
    {
        $this->events = [];
    }
}
