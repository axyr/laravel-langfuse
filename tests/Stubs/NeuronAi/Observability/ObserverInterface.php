<?php

declare(strict_types=1);

namespace NeuronAI\Observability;

interface ObserverInterface
{
    public function onEvent(string $event, object $source, mixed $data = null): void;
}
