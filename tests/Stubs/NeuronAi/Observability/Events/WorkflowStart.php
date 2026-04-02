<?php

declare(strict_types=1);

namespace NeuronAI\Observability\Events;

class WorkflowStart
{
    public function __construct(public array $eventNodeMap = []) {}
}
