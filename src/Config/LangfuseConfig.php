<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Config;

use InvalidArgumentException;

readonly class LangfuseConfig
{
    public const ENVIRONMENT_PATTERN = '/^(?!langfuse)[a-z0-9_-]{1,40}$/';

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
        public ?string $environment = null,
        public bool $userTracingEnabled = true,
        public bool $sessionTracingEnabled = true,
        public string $serviceName = 'laravel',
        public bool $compression = false,
        public ?string $release = null,
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
            environment: self::parseEnvironment($config['environment'] ?? null),
            userTracingEnabled: self::parseBool($config['user_tracing'] ?? true),
            sessionTracingEnabled: self::parseBool($config['session_tracing'] ?? true),
            serviceName: self::parseString($config['service_name'] ?? null, 'laravel'),
            compression: self::parseBool($config['compression'] ?? false),
            release: self::parseNullableString($config['release'] ?? null),
        );
    }

    /**
     * Langfuse environments are immutable once ingested and are rejected server-side
     * when malformed, so an invalid value is refused up front instead of silently
     * dropping every event.
     */
    private static function parseEnvironment(mixed $value): ?string
    {
        $environment = self::parseNullableString($value);

        if ($environment === null) {
            return null;
        }

        if (preg_match(self::ENVIRONMENT_PATTERN, $environment) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Invalid Langfuse environment "%s": it must match %s (lowercase letters, digits, hyphens and underscores, max 40 characters, not starting with "langfuse").',
                $environment,
                self::ENVIRONMENT_PATTERN,
            ));
        }

        return $environment;
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
     * The OTLP endpoint that replaces the deprecated ingestion endpoint for
     * observations. Scores keep using ingestionUrl().
     */
    public function otelTracesUrl(): string
    {
        return rtrim($this->baseUrl, '/') . '/api/public/otel/v1/traces';
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

    public function scoresV3Url(): string
    {
        return rtrim($this->baseUrl, '/') . '/api/public/v3/scores';
    }

    public function observationsV2Url(): string
    {
        return rtrim($this->baseUrl, '/') . '/api/public/v2/observations';
    }

    public function metricsV2Url(): string
    {
        return rtrim($this->baseUrl, '/') . '/api/public/v2/metrics';
    }

    public function datasetsUrl(?string $datasetName = null): string
    {
        $url = rtrim($this->baseUrl, '/') . '/api/public/v2/datasets';

        if ($datasetName !== null) {
            $url .= '/' . urlencode($datasetName);
        }

        return $url;
    }

    public function datasetItemsUrl(?string $id = null): string
    {
        $url = rtrim($this->baseUrl, '/') . '/api/public/dataset-items';

        if ($id !== null) {
            $url .= '/' . urlencode($id);
        }

        return $url;
    }

    public function experimentsUrl(): string
    {
        return rtrim($this->baseUrl, '/') . '/api/public/experiments';
    }

    public function experimentItemsUrl(): string
    {
        return rtrim($this->baseUrl, '/') . '/api/public/experiment-items';
    }

    public function promptsUrl(?string $name = null): string
    {
        $url = rtrim($this->baseUrl, '/') . '/api/public/v2/prompts';

        if ($name !== null) {
            $url .= '/' . urlencode($name);
        }

        return $url;
    }

    /**
     * Determines if Prism tracing should be enabled.
     *
     * Prism is automatically enabled when Laravel AI is enabled,
     * since Laravel AI uses Prism under the hood.
     */
    public function shouldEnablePrism(): bool
    {
        return $this->prismEnabled || $this->laravelAiEnabled;
    }
}
