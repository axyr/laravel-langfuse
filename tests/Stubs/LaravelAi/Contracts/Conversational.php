<?php

declare(strict_types=1);

namespace Laravel\Ai\Contracts;

interface Conversational
{
    public function messages(): iterable;
}
