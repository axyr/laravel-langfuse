<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Otlp;

use Axyr\Langfuse\Dto\ExperimentContext;
use Axyr\Langfuse\Dto\Otlp\OtlpAttribute;
use Axyr\Langfuse\Dto\TraceBody;

/**
 * The attributes every span of a trace carries. In v4 trace-level values live on
 * each observation row, and the observations and metrics APIs filter on them, so
 * a child without them is invisible to those filters.
 */
class TraceAttributes
{
    public function __construct(
        private readonly AttributeEncoder $encoder = new AttributeEncoder(),
    ) {}

    /**
     * @return array<string, OtlpAttribute> keyed by attribute key
     */
    public function forTrace(TraceBody $body): array
    {
        $attributes = $this->encoder->keyed([
            $this->encoder->scalar('langfuse.trace.name', $body->name),
            $this->encoder->scalar('langfuse.user.id', $body->userId),
            $this->encoder->scalar('langfuse.session.id', $body->sessionId),
            $this->encoder->stringList('langfuse.trace.tags', $body->tags),
            $this->encoder->scalar('langfuse.release', $body->release),
            $this->encoder->scalar('langfuse.version', $body->version),
            $this->encoder->scalar('langfuse.trace.public', $body->public),
            $this->encoder->scalar('langfuse.environment', $body->environment),
        ]);

        return array_merge(
            $attributes,
            $this->encoder->metadata('langfuse.trace.metadata.', $body->metadata),
            $this->forExperiment($body->experiment),
        );
    }

    /**
     * @return array<string, OtlpAttribute>
     */
    private function forExperiment(?ExperimentContext $experiment): array
    {
        if ($experiment === null) {
            return [];
        }

        $attributes = $this->encoder->keyed([
            $this->encoder->scalar('langfuse.experiment.id', $experiment->id),
            $this->encoder->scalar('langfuse.experiment.name', $experiment->name),
            $this->encoder->scalar('langfuse.experiment.dataset.id', $experiment->datasetId),
            $this->encoder->scalar('langfuse.experiment.description', $experiment->description),
        ]);

        return array_merge(
            $attributes,
            $this->encoder->metadata('langfuse.experiment.metadata.', $experiment->metadata),
        );
    }
}
