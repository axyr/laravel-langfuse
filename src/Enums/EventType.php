<?php

declare(strict_types=1);

namespace Langfuse\Enums;

enum EventType: string
{
    case TraceCreate = 'trace-create';
    case SpanCreate = 'span-create';
    case SpanUpdate = 'span-update';
    case GenerationCreate = 'generation-create';
    case GenerationUpdate = 'generation-update';
    case EventCreate = 'event-create';
    case ScoreCreate = 'score-create';
}
