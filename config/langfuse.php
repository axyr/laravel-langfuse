<?php

declare(strict_types=1);

return [
    'public_key' => env('LANGFUSE_PUBLIC_KEY', ''),
    'secret_key' => env('LANGFUSE_SECRET_KEY', ''),
    'base_url' => env('LANGFUSE_BASE_URL', 'https://cloud.langfuse.com'),
    'enabled' => env('LANGFUSE_ENABLED', true),
    'flush_at' => env('LANGFUSE_FLUSH_AT', 10),
    'request_timeout' => env('LANGFUSE_REQUEST_TIMEOUT', 15),
    'prompt_cache_ttl' => env('LANGFUSE_PROMPT_CACHE_TTL', 60),
    'prism_enabled' => env('LANGFUSE_PRISM_ENABLED', false),
    'laravel_ai_enabled' => env('LANGFUSE_LARAVEL_AI_ENABLED', false),
    'neuron_ai_enabled' => env('LANGFUSE_NEURON_AI_ENABLED', false),
    'queue' => env('LANGFUSE_QUEUE', null),
];
