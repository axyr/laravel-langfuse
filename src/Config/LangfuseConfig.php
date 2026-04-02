<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Config;

readonly class LangfuseConfig
{
    public function __construct(
        public string $publicKey,
        public string $secretKey,
        public string $baseUrl = 'https://cloud.langfuse.com',
        public bool $enabled = true,
        public int $flushAt = 10,
        public int $requestTimeout = 15,
        public int $promptCacheTtl = 60,
        public bool $prismEnabled = false,
        public bool $laravelAiEnabled = false,
        public bool $neuronAiEnabled = false,
        public ?string $queue = null,
    ) {}

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            publicKey: self::parseString($config['public_key'] ?? null, ''),
            secretKey: self::parseString($config['secret_key'] ?? null, ''),
            baseUrl: self::parseString($config['base_url'] ?? null, 'https://cloud.langfuse.com'),
            enabled: self::parseBool($config['enabled'] ?? true),
            flushAt: self::parseInt($config['flush_at'] ?? null, 10),
            requestTimeout: self::parseInt($config['request_timeout'] ?? null, 15),
            promptCacheTtl: self::parseInt($config['prompt_cache_ttl'] ?? null, 60),
            prismEnabled: self::parseBool($config['prism_enabled'] ?? false),
            laravelAiEnabled: self::parseBool($config['laravel_ai_enabled'] ?? false),
            neuronAiEnabled: self::parseBool($config['neuron_ai_enabled'] ?? false),
            queue: self::parseNullableString($config['queue'] ?? null),
        );
    }

    private static function parseNullableString(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }

    private static function parseString(mixed $value, string $default): string
    {
        if (is_string($value)) {
            return $value;
        }

        return $default;
    }

    private static function parseInt(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) || is_float($value)) {
            return (int) $value;
        }

        return $default;
    }

    private static function parseBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return (bool) $value;
        }

        return ! in_array(strtolower($value), ['false', '0', 'no', 'off', ''], true);
    }

    public function authHeader(): string
    {
        return 'Basic ' . base64_encode($this->publicKey . ':' . $this->secretKey);
    }

    /**
     * @return array<string, mixed>
     */
    public function batchMetadata(int $batchSize): array
    {
        return [
            'batch_size' => $batchSize,
            'sdk_name' => 'langfuse-php',
            'sdk_version' => '0.1.0',
            'public_key' => $this->publicKey,
        ];
    }

    public function ingestionUrl(): string
    {
        return rtrim($this->baseUrl, '/') . '/api/public/ingestion';
    }

    public function scoresUrl(?string $scoreId = null): string
    {
        $url = rtrim($this->baseUrl, '/') . '/api/public/scores';

        if ($scoreId !== null) {
            $url .= '/' . urlencode($scoreId);
        }

        return $url;
    }

    public function promptsUrl(?string $name = null): string
    {
        $url = rtrim($this->baseUrl, '/') . '/api/public/v2/prompts';

        if ($name !== null) {
            $url .= '/' . urlencode($name);
        }

        return $url;
    }
}
