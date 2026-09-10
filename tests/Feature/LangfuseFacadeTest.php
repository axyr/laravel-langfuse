<?php

declare(strict_types=1);

use Axyr\Langfuse\Contracts\LangfuseClientInterface;
use Axyr\Langfuse\Dto\IdGenerator;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\LangfuseClient;
use Axyr\Langfuse\LangfuseFacade;
use Axyr\Langfuse\Objects\LangfuseTrace;
use Illuminate\Support\Facades\Http;

it('resolves to LangfuseClientInterface', function () {
    $resolved = LangfuseFacade::getFacadeRoot();

    expect($resolved)->toBeInstanceOf(LangfuseClientInterface::class)
        ->and($resolved)->toBeInstanceOf(LangfuseClient::class);
});

it('proxies trace method', function () {
    Http::fake();

    $trace = LangfuseFacade::trace(new TraceBody(id: 'trace-1', name: 'facade-test'));

    expect($trace)->toBeInstanceOf(LangfuseTrace::class)
        ->and($trace->getId())->toBe(IdGenerator::traceIdFromSeed('trace-1'))
        ->and($trace->getRootObservationId())->toMatch('/^[0-9a-f]{16}$/');
});

it('proxies score method', function () {
    Http::fake();

    LangfuseFacade::score(new ScoreBody(
        id: 'score-1',
        traceId: 'trace-1',
        name: 'accuracy',
        value: 0.95,
    ));

    // No exception means success - score was enqueued
    expect(true)->toBeTrue();
});

it('proxies flush method', function () {
    Http::fake();

    LangfuseFacade::flush();

    expect(true)->toBeTrue();
});

it('proxies shutdown method', function () {
    Http::fake();

    LangfuseFacade::trace(new TraceBody(name: 'shutdown-test'));
    LangfuseFacade::shutdown();

    expect(true)->toBeTrue();
});

it('proxies isEnabled method', function () {
    expect(LangfuseFacade::isEnabled())->toBeTrue();
});
