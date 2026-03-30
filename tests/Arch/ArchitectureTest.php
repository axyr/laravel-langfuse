<?php

declare(strict_types=1);

arch('enums are enums')
    ->expect('Langfuse\Enums')
    ->toBeEnums();

arch('enums are string-backed')
    ->expect('Langfuse\Enums')
    ->toBeStringBackedEnums();

arch('source uses strict types')
    ->expect('Langfuse')
    ->toUseStrictTypes();

arch('dtos are readonly')
    ->expect('Langfuse\Dto')
    ->toBeReadonly();

arch('config is readonly')
    ->expect('Langfuse\Config')
    ->toBeReadonly();

arch('contracts are interfaces')
    ->expect('Langfuse\Contracts')
    ->toBeInterfaces();

arch('dtos do not depend on Laravel')
    ->expect('Langfuse\Dto')
    ->toUseNothing()
    ->ignoring('Langfuse\Contracts')
    ->ignoring('Langfuse\Enums')
    ->ignoring('Langfuse\Dto');

arch('only api client uses Http facade')
    ->expect('Illuminate\Support\Facades\Http')
    ->toOnlyBeUsedIn('Langfuse\Api\IngestionApiClient');

arch('facade extends base facade')
    ->expect('Langfuse\LangfuseFacade')
    ->toExtend('Illuminate\Support\Facades\Facade');

arch('service provider extends base provider')
    ->expect('Langfuse\LangfuseServiceProvider')
    ->toExtend('Illuminate\Support\ServiceProvider');

arch('dtos implement serializable interface')
    ->expect('Langfuse\Dto\TraceBody')
    ->toImplement('Langfuse\Contracts\SerializableInterface');

arch('client implements client interface')
    ->expect('Langfuse\LangfuseClient')
    ->toImplement('Langfuse\Contracts\LangfuseClientInterface');
