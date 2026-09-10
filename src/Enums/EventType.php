<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Enums;

/**
 * Observations moved to the OTLP endpoint in v4; `score-create` is the only
 * ingestion event type this package still sends.
 */
enum EventType: string
{
    case ScoreCreate = 'score-create';
}
