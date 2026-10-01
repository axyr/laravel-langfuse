<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Otlp;

use Axyr\Langfuse\Dto\EventBody;
use Axyr\Langfuse\Dto\ExperimentItemContext;
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\Otlp\OtlpAttribute;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Enums\ObservationType;
use Axyr\Langfuse\Tracing\CompletedObservation;

/**
 * The attributes an individual observation contributes. They are applied after
 * the trace-level ones, so `langfuse.version` and `langfuse.environment` set on
 * the observation win for that span.
 */
class ObservationAttributes
{
    public function __construct(
        private readonly AttributeEncoder $encoder = new AttributeEncoder(),
    ) {}

    /**
     * @return array<string, OtlpAttribute>
     */
    public function forObservation(CompletedObservation $observation): array
    {
        $body = $observation->body;

        if ($body instanceof TraceBody) {
            return $this->forRoot($body, $observation->observationId());
        }

        $attributes = array_merge(
            $this->common($body, $observation->type()),
            $this->encoder->metadata('langfuse.observation.metadata.', $body->metadata),
        );

        if ($body instanceof GenerationBody) {
            return array_merge($attributes, $this->forGeneration($body));
        }

        return $attributes;
    }

    /**
     * @return array<string, OtlpAttribute>
     */
    private function common(SpanBody|GenerationBody|EventBody $body, ObservationType $type): array
    {
        return $this->encoder->keyed([
            $this->encoder->scalar('langfuse.observation.type', $type->value),
            $this->encoder->scalar('langfuse.observation.level', $body->level?->value),
            $this->encoder->scalar('langfuse.observation.status_message', $body->statusMessage),
            $this->encoder->text('langfuse.observation.input', $body->input),
            $this->encoder->text('langfuse.observation.output', $body->output),
            $this->encoder->scalar('langfuse.version', $body->version),
            $this->encoder->scalar('langfuse.environment', $body->environment),
        ]);
    }

    /**
     * @return array<string, OtlpAttribute>
     */
    private function forGeneration(GenerationBody $body): array
    {
        $usage = $body->usage;

        return $this->encoder->keyed([
            $this->encoder->scalar('langfuse.observation.model.name', $body->model),
            $this->encoder->text('langfuse.observation.model.parameters', $body->modelParameters),
            $this->details('langfuse.observation.usage_details', $usage?->usageDetails()),
            $this->details('langfuse.observation.cost_details', $usage?->costDetails()),
            $this->encoder->scalar('langfuse.observation.prompt.name', $body->promptName),
            $this->encoder->scalar('langfuse.observation.prompt.version', $body->promptVersion),
            $this->encoder->scalar('langfuse.observation.completion_start_time', $body->completionStartTime),
        ]);
    }

    /**
     * @param array<string, int|float>|null $details
     */
    private function details(string $key, ?array $details): ?OtlpAttribute
    {
        if ($details === null || $details === []) {
            return null;
        }

        return OtlpAttribute::string($key, $this->encoder->json($details));
    }

    /**
     * The root observation carries the trace's own input and output; the
     * deprecated `langfuse.trace.input`/`.output` are not emitted.
     *
     * @return array<string, OtlpAttribute>
     */
    private function forRoot(TraceBody $body, string $rootObservationId): array
    {
        $attributes = $this->encoder->keyed([
            $this->encoder->scalar('langfuse.observation.type', ObservationType::Span->value),
            $this->encoder->text('langfuse.observation.input', $body->input),
            $this->encoder->text('langfuse.observation.output', $body->output),
        ]);

        return array_merge($attributes, $this->forExperimentItem($body->experimentItem, $rootObservationId));
    }

    /**
     * @return array<string, OtlpAttribute>
     */
    private function forExperimentItem(?ExperimentItemContext $item, string $rootObservationId): array
    {
        if ($item === null) {
            return [];
        }

        $attributes = $this->encoder->keyed([
            $this->encoder->scalar('langfuse.experiment.item.id', $item->itemId),
            $this->encoder->scalar('langfuse.experiment.item.root_observation_id', $rootObservationId),
            $this->encoder->text('langfuse.experiment.item.expected_output', $item->expectedOutput),
            $this->encoder->scalar('langfuse.experiment.item.version', $item->version),
        ]);

        return array_merge(
            $attributes,
            $this->encoder->metadata('langfuse.experiment.item.metadata.', $item->metadata),
        );
    }
}
