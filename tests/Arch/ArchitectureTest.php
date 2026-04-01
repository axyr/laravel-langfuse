<?php

declare(strict_types=1);

arch('enums are enums')
    ->expect('Axyr\\Langfuse\\Enums')
    ->toBeEnums();

arch('enums are string-backed')
    ->expect('Axyr\\Langfuse\\Enums')
    ->toBeStringBackedEnums();

arch('source uses strict types')
    ->expect('Axyr\\Langfuse')
    ->toUseStrictTypes();

arch('dtos are readonly')
    ->expect('Axyr\\Langfuse\\Dto')
    ->toBeReadonly();

arch('config is readonly')
    ->expect('Axyr\\Langfuse\\Config')
    ->toBeReadonly();

arch('contracts are interfaces')
    ->expect('Axyr\\Langfuse\\Contracts')
    ->toBeInterfaces();

arch('dtos do not depend on Laravel')
    ->expect('Axyr\\Langfuse\\Dto')
    ->toUseNothing()
    ->ignoring('Axyr\\Langfuse\\Contracts')
    ->ignoring('Axyr\\Langfuse\\Enums')
    ->ignoring('Axyr\\Langfuse\\Dto');

arch('only api clients use Http facade')
    ->expect('Illuminate\Support\Facades\Http')
    ->toOnlyBeUsedIn([
        'Axyr\\Langfuse\\Api\IngestionApiClient',
        'Axyr\\Langfuse\\Api\PromptApiClient',
        'Axyr\\Langfuse\\Api\ScoreApiClient',
    ]);

arch('facade extends base facade')
    ->expect('Axyr\\Langfuse\\LangfuseFacade')
    ->toExtend('Illuminate\Support\Facades\Facade');

arch('service provider extends base provider')
    ->expect('Axyr\\Langfuse\\LangfuseServiceProvider')
    ->toExtend('Illuminate\Support\ServiceProvider');

arch('dtos implement serializable interface')
    ->expect('Axyr\\Langfuse\\Dto\TraceBody')
    ->toImplement('Axyr\\Langfuse\\Contracts\SerializableInterface');

arch('client implements client interface')
    ->expect('Axyr\\Langfuse\\LangfuseClient')
    ->toImplement('Axyr\\Langfuse\\Contracts\LangfuseClientInterface');

arch('prompt dtos implement prompt interface')
    ->expect('Axyr\\Langfuse\\Dto\TextPrompt')
    ->toImplement('Axyr\\Langfuse\\Contracts\PromptInterface');

arch('chat prompt dtos implement prompt interface')
    ->expect('Axyr\\Langfuse\\Dto\ChatPrompt')
    ->toImplement('Axyr\\Langfuse\\Contracts\PromptInterface');

arch('exceptions extend RuntimeException')
    ->expect('Axyr\\Langfuse\\Exceptions')
    ->toExtend('RuntimeException');

arch('fake implements client interface')
    ->expect('Axyr\\Langfuse\\Testing\LangfuseFake')
    ->toImplement('Axyr\\Langfuse\\Contracts\LangfuseClientInterface');

arch('recording batcher implements batcher interface')
    ->expect('Axyr\\Langfuse\\Testing\RecordingEventBatcher')
    ->toImplement('Axyr\\Langfuse\\Contracts\EventBatcherInterface');

arch('tracing provider extends prism provider')
    ->expect('Axyr\\Langfuse\\Prism\TracingProvider')
    ->toExtend('Prism\Prism\Providers\Provider');

arch('tracing manager extends prism manager')
    ->expect('Axyr\\Langfuse\\Prism\TracingPrismManager')
    ->toExtend('Prism\Prism\PrismManager');
