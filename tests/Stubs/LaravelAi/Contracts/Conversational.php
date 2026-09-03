<?php

declare(strict_types=1);

namespace Laravel\Ai\Contracts;

interface Conversational
{
    /**
     * Get the list of messages comprising the conversation so far.
     */
    public function messages(): iterable;
}
