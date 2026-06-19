<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Api\Concerns;

trait SerializesQueryParameters
{
    /**
     * Serialise query parameters so array values become repeated bare keys
     * (e.g. `environment=prod&environment=dev`), matching the Langfuse API's
     * form/explode style. PHP/Guzzle would otherwise emit indexed keys
     * (`environment[0]=prod`), which the API does not bind.
     *
     * @param  array<string, mixed>  $params
     */
    private function buildQueryString(array $params): string
    {
        $pairs = [];

        foreach ($params as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $pairs[] = rawurlencode((string) $key) . '=' . rawurlencode($this->stringifyParam($item));
                }

                continue;
            }

            $pairs[] = rawurlencode((string) $key) . '=' . rawurlencode($this->stringifyParam($value));
        }

        return implode('&', $pairs);
    }

    private function stringifyParam(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return '';
    }
}
