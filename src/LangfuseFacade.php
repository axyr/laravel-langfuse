<?php

declare(strict_types=1);

namespace Axyr\Langfuse;

use Axyr\Langfuse\Contracts\LangfuseClientInterface;
use Axyr\Langfuse\Testing\LangfuseFake;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Axyr\Langfuse\Objects\LangfuseTrace trace(\Axyr\Langfuse\Dto\TraceBody $body)
 * @method static \Axyr\Langfuse\Objects\LangfuseTrace|null currentTrace()
 * @method static void setCurrentTrace(\Axyr\Langfuse\Objects\LangfuseTrace $trace)
 * @method static void score(\Axyr\Langfuse\Dto\ScoreBody $body)
 * @method static void flush()
 * @method static bool isEnabled()
 * @method static \Axyr\Langfuse\Contracts\PromptInterface prompt(string $name, ?int $version = null, ?string $label = null, string|array<int, array<string, string>>|null $fallback = null)
 *
 * @see \Axyr\Langfuse\LangfuseClient
 */
class LangfuseFacade extends Facade
{
    public static function fake(): LangfuseFake
    {
        $fake = new LangfuseFake();
        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return LangfuseClientInterface::class;
    }
}
