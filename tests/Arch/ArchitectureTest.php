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

arch('only api clients use Http facade')
    ->expect('Illuminate\Support\Facades\Http')
    ->toOnlyBeUsedIn([
        'Langfuse\Api\IngestionApiClient',
        'Langfuse\Api\PromptApiClient',
        'Langfuse\Api\ScoreApiClient',
    ]);

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

arch('prompt dtos implement prompt interface')
    ->expect('Langfuse\Dto\TextPrompt')
    ->toImplement('Langfuse\Contracts\PromptInterface');

arch('chat prompt dtos implement prompt interface')
    ->expect('Langfuse\Dto\ChatPrompt')
    ->toImplement('Langfuse\Contracts\PromptInterface');

arch('exceptions extend RuntimeException')
    ->expect('Langfuse\Exceptions')
    ->toExtend('RuntimeException');

arch('fake implements client interface')
    ->expect('Langfuse\Testing\LangfuseFake')
    ->toImplement('Langfuse\Contracts\LangfuseClientInterface');

arch('recording batcher implements batcher interface')
    ->expect('Langfuse\Testing\RecordingEventBatcher')
    ->toImplement('Langfuse\Contracts\EventBatcherInterface');

arch('tracing provider extends prism provider')
    ->expect('Langfuse\Prism\TracingProvider')
    ->toExtend('Prism\Prism\Providers\Provider');

arch('tracing manager extends prism manager')
    ->expect('Langfuse\Prism\TracingPrismManager')
    ->toExtend('Prism\Prism\PrismManager');
