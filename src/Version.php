<?php

declare(strict_types=1);

namespace Axyr\Langfuse;

/**
 * Single source of truth for the SDK identity that Langfuse sees, used for the
 * OTLP resource attributes, the OTLP instrumentation scope and the score batch
 * metadata. Bump SDK_VERSION with every release.
 */
final class Version
{
    public const SDK_NAME = 'langfuse-php';

    public const SDK_LANGUAGE = 'php';

    public const SDK_VERSION = '0.4.0';
}
