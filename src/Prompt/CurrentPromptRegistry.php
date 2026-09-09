<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Prompt;

use Axyr\Langfuse\Contracts\PromptInterface;

/**
 * Holds the most recently resolved managed prompt so auto-instrumentation can
 * link it to the generation it produced (promptName/promptVersion).
 *
 * The client registers every non-fallback prompt it resolves; integrations
 * consume() it when they start recording a generation. Consuming clears the
 * registry so a prompt is linked to at most one generation.
 */
class CurrentPromptRegistry
{
    private ?PromptInterface $prompt = null;

    public function set(PromptInterface $prompt): void
    {
        if ($prompt->isFallback()) {
            return;
        }

        $this->prompt = $prompt;
    }

    public function current(): ?PromptInterface
    {
        return $this->prompt;
    }

    public function consume(): ?PromptInterface
    {
        $prompt = $this->prompt;
        $this->prompt = null;

        return $prompt;
    }
}
