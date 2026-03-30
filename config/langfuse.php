<?php

declare(strict_types=1);

return [
    'public_key' => env('LANGFUSE_PUBLIC_KEY', ''),
    'secret_key' => env('LANGFUSE_SECRET_KEY', ''),
    'base_url' => env('LANGFUSE_BASE_URL', 'https://cloud.langfuse.com'),
    'enabled' => env('LANGFUSE_ENABLED', true),
    'flush_at' => env('LANGFUSE_FLUSH_AT', 10),
    'request_timeout' => env('LANGFUSE_REQUEST_TIMEOUT', 15),
];
