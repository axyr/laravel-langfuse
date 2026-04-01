<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

use Axyr\Langfuse\Contracts\PromptInterface;

readonly class PromptFactory
{
    /**
     * @param array<string, mixed> $data
     */
    public static function fromApiResponse(array $data): PromptInterface
    {
        /** @var string $type */
        $type = $data['type'] ?? 'text';

        if ($type === 'chat') {
            return self::createChatFromApi($data);
        }

        return self::createTextFromApi($data);
    }

    public static function fallbackText(string $name, string $prompt): PromptInterface
    {
        return new TextPrompt(
            name: $name,
            version: 0,
            prompt: $prompt,
            fallback: true,
        );
    }

    /**
     * @param array<int, array<string, string>> $messages
     */
    public static function fallbackChat(string $name, array $messages): PromptInterface
    {
        return new ChatPrompt(
            name: $name,
            version: 0,
            messages: $messages,
            fallback: true,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createTextFromApi(array $data): TextPrompt
    {
        /** @var string $name */
        $name = $data['name'] ?? '';

        /** @var int $version */
        $version = $data['version'] ?? 1;

        /** @var string $prompt */
        $prompt = $data['prompt'] ?? '';

        /** @var array<string, mixed> $config */
        $config = $data['config'] ?? [];

        /** @var array<string> $labels */
        $labels = $data['labels'] ?? [];

        return new TextPrompt(
            name: $name,
            version: $version,
            prompt: $prompt,
            config: $config,
            labels: $labels,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createChatFromApi(array $data): ChatPrompt
    {
        /** @var string $name */
        $name = $data['name'] ?? '';

        /** @var int $version */
        $version = $data['version'] ?? 1;

        /** @var array<int, array<string, string>> $messages */
        $messages = $data['prompt'] ?? [];

        /** @var array<string, mixed> $config */
        $config = $data['config'] ?? [];

        /** @var array<string> $labels */
        $labels = $data['labels'] ?? [];

        return new ChatPrompt(
            name: $name,
            version: $version,
            messages: $messages,
            config: $config,
            labels: $labels,
        );
    }
}
