<?php

declare(strict_types=1);

namespace Langfuse;

use Illuminate\Support\Facades\Facade;
use Langfuse\Contracts\LangfuseClientInterface;

/**
 * @method static \Langfuse\Objects\LangfuseTrace trace(\Langfuse\Dto\TraceBody $body)
 * @method static void score(\Langfuse\Dto\ScoreBody $body)
 * @method static void flush()
 * @method static bool isEnabled()
 *
 * @see \Langfuse\LangfuseClient
 */
class LangfuseFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LangfuseClientInterface::class;
    }
}
