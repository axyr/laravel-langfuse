<?php

declare(strict_types=1);

use Axyr\Langfuse\Http\Middleware\LangfuseMiddleware;
use Axyr\Langfuse\LangfuseFacade as Langfuse;
use Axyr\Langfuse\Objects\NullLangfuseTrace;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

it('creates a trace for the request', function () {
    $fake = Langfuse::fake();

    $middleware = app(LangfuseMiddleware::class);
    $request = Request::create('/api/chat', 'POST');

    $middleware->handle($request, fn() => new Response('OK'));

    $fake->assertTraceCreated('POST api/chat');
});

it('uses route name when available', function () {
    $fake = Langfuse::fake();

    $middleware = app(LangfuseMiddleware::class);
    $request = Request::create('/api/chat', 'POST');

    $route = new \Illuminate\Routing\Route('POST', '/api/chat', fn() => 'ok');
    $route->name('api.chat');
    $request->setRouteResolver(fn() => $route);

    $middleware->handle($request, fn() => new Response('OK'));

    $fake->assertTraceCreated('api.chat');
});

it('sets current trace on the client', function () {
    $fake = Langfuse::fake();

    $middleware = app(LangfuseMiddleware::class);
    $request = Request::create('/test', 'GET');

    $middleware->handle($request, fn() => new Response('OK'));

    expect($fake->currentTrace())->not->toBeInstanceOf(NullLangfuseTrace::class);
});

it('skips trace creation when disabled', function () {
    config(['langfuse.enabled' => false]);

    $this->app->forgetScopedInstances();
    $this->app->forgetInstance(\Axyr\Langfuse\Config\LangfuseConfig::class);

    $middleware = app(LangfuseMiddleware::class);
    $request = Request::create('/test', 'GET');

    $response = $middleware->handle($request, fn() => new Response('OK'));

    expect($response->getStatusCode())->toBe(200);
});

it('passes response through unchanged', function () {
    Langfuse::fake();

    $middleware = app(LangfuseMiddleware::class);
    $request = Request::create('/test', 'GET');
    $expectedResponse = new Response('Hello World', 200);

    $response = $middleware->handle($request, fn() => $expectedResponse);

    expect($response)->toBe($expectedResponse);
});

it('includes metadata in trace', function () {
    $fake = Langfuse::fake();

    $middleware = app(LangfuseMiddleware::class);
    $request = Request::create('/api/chat', 'POST');

    $middleware->handle($request, fn() => new Response('OK'));

    $traceEvent = collect($fake->events())->first(fn($e) => $e->type->value === 'trace-create');
    $body = $traceEvent->body->toArray();

    expect($body['metadata'])->toHaveKey('method')
        ->and($body['metadata']['method'])->toBe('POST')
        ->and($body['metadata'])->toHaveKey('source')
        ->and($body['metadata']['source'])->toBe('langfuse-middleware');
});
