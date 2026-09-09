<?php

declare(strict_types=1);

use Axyr\Langfuse\Tracing\AuthTraceContextResolver;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Container\Container;

function makeAuthContainer(?Guard $guard): Container
{
    $container = Mockery::mock(Container::class);
    $container->shouldReceive('bound')->with(AuthFactory::class)->andReturn($guard !== null);

    if ($guard !== null) {
        $factory = Mockery::mock(AuthFactory::class);
        $factory->shouldReceive('guard')->andReturn($guard);
        $container->shouldReceive('make')->with(AuthFactory::class)->andReturn($factory);
    }

    return $container;
}

it('returns null when auth is not bound', function () {
    $resolver = new AuthTraceContextResolver(makeAuthContainer(null));

    expect($resolver->resolveUserId())->toBeNull();
});

it('does not authenticate a guard that has no user yet', function () {
    $guard = Mockery::mock(Guard::class);
    $guard->shouldReceive('hasUser')->once()->andReturn(false);
    $guard->shouldNotReceive('user');

    $resolver = new AuthTraceContextResolver(makeAuthContainer($guard));

    expect($resolver->resolveUserId())->toBeNull();
});

it('returns the id of the already authenticated user', function () {
    $guard = Mockery::mock(Guard::class);
    $guard->shouldReceive('hasUser')->andReturn(true);
    $guard->shouldReceive('user')->andReturn(new GenericUser(['id' => 42]));

    $resolver = new AuthTraceContextResolver(makeAuthContainer($guard));

    expect($resolver->resolveUserId())->toBe('42');
});

it('has no default session', function () {
    $resolver = new AuthTraceContextResolver(makeAuthContainer(null));

    expect($resolver->resolveSessionId())->toBeNull();
});
