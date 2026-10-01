<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Enums;

/**
 * Values Langfuse v4 recognises for `langfuse.observation.type`. Spans default
 * to `Span`; the others let an integration mark what a span actually did.
 */
enum ObservationType: string
{
    case Span = 'span';
    case Generation = 'generation';
    case Event = 'event';
    case Agent = 'agent';
    case Tool = 'tool';
    case Chain = 'chain';
    case Retriever = 'retriever';
    case Evaluator = 'evaluator';
    case Embedding = 'embedding';
    case Guardrail = 'guardrail';
}
