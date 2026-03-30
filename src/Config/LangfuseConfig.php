<?php

declare(strict_types=1);

namespace Langfuse\Config;

readonly class LangfuseConfig
{
    public function __construct(
        public string $publicKey,
        public string $secretKey,
        public string $baseUrl = 'https://cloud.langfuse.com',
        public bool $enabled = true,
        public int $flushAt = 10,
        public int $requestTimeout = 15,
    ) {}

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            publicKey: strval($config['public_key'] ?? ''),
            secretKey: strval($config['secret_key'] ?? ''),
            baseUrl: strval($config['base_url'] ?? 'https://cloud.langfuse.com'),
            enabled: self::parseBool($config['enabled'] ?? true),
            flushAt: intval($config['flush_at'] ?? 10),
            requestTimeout: intval($config['request_timeout'] ?? 15),
        );
    }

    private static function parseBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return ! in_array(strtolower(strval($value)), ['false', '0', 'no', 'off', ''], true);
    }

    public function authHeader(): string
    {
        return 'Basic ' . base64_encode($this->publicKey . ':' . $this->secretKey);
    }

    public function ingestionUrl(): string
    {
        return rtrim($this->baseUrl, '/') . '/api/public/ingestion';
    }
}
