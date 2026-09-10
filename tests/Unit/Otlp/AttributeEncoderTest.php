<?php

declare(strict_types=1);

use Axyr\Langfuse\Otlp\AttributeEncoder;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->encoder = new AttributeEncoder();
});

it('drops null values', function () {
    expect($this->encoder->scalar('k', null))->toBeNull()
        ->and($this->encoder->text('k', null))->toBeNull()
        ->and($this->encoder->stringList('k', null))->toBeNull()
        ->and($this->encoder->stringList('k', []))->toBeNull();
});

it('encodes each scalar as its own otlp value kind', function () {
    expect($this->encoder->scalar('k', 'text')?->value)->toBe(['stringValue' => 'text'])
        ->and($this->encoder->scalar('k', 7)?->value)->toBe(['intValue' => 7])
        ->and($this->encoder->scalar('k', 1.5)?->value)->toBe(['doubleValue' => 1.5])
        ->and($this->encoder->scalar('k', true)?->value)->toBe(['boolValue' => true])
        ->and($this->encoder->scalar('k', false)?->value)->toBe(['boolValue' => false]);
});

it('json encodes a non-scalar into a string value', function () {
    expect($this->encoder->scalar('k', ['a' => 1])?->value)->toBe(['stringValue' => '{"a":1}']);
});

it('passes a string through as text and json encodes anything else', function () {
    expect($this->encoder->text('k', 'plain')?->value)->toBe(['stringValue' => 'plain'])
        ->and($this->encoder->text('k', ['a' => 1])?->value)->toBe(['stringValue' => '{"a":1}'])
        ->and($this->encoder->text('k', 42)?->value)->toBe(['stringValue' => '42']);
});

it('encodes a list as an otlp array value', function () {
    expect($this->encoder->stringList('k', ['one', 'two'])?->value)->toBe([
        'arrayValue' => ['values' => [['stringValue' => 'one'], ['stringValue' => 'two']]],
    ]);
});

it('does not escape slashes or unicode', function () {
    expect($this->encoder->json(['url' => 'https://langfuse.com/docs', 'text' => 'héllo']))
        ->toBe('{"url":"https://langfuse.com/docs","text":"héllo"}');
});

it('falls back to readable text and logs when encoding fails', function () {
    Log::shouldReceive('warning')->once()->with('Langfuse attribute encoding failed', Mockery::any());

    $encoded = $this->encoder->json(["\xB1\x31"]);

    expect($encoded)->toBeString()
        ->and($encoded)->toContain('Array');
});

it('prefixes metadata keys and keys the result by attribute name', function () {
    $attributes = $this->encoder->metadata('langfuse.trace.metadata.', [
        'source' => 'test',
        'retries' => 3,
        'nested' => ['a' => 1],
    ]);

    expect(array_keys($attributes))->toBe([
        'langfuse.trace.metadata.source',
        'langfuse.trace.metadata.retries',
        'langfuse.trace.metadata.nested',
    ])
        ->and($attributes['langfuse.trace.metadata.source']->value)->toBe(['stringValue' => 'test'])
        ->and($attributes['langfuse.trace.metadata.retries']->value)->toBe(['intValue' => 3])
        ->and($attributes['langfuse.trace.metadata.nested']->value)->toBe(['stringValue' => '{"a":1}']);
});

it('returns an empty metadata set for null', function () {
    expect($this->encoder->metadata('prefix.', null))->toBe([]);
});

it('keys attributes and drops the nulls', function () {
    $keyed = $this->encoder->keyed([
        $this->encoder->scalar('a', 1),
        null,
        $this->encoder->scalar('b', 2),
    ]);

    expect(array_keys($keyed))->toBe(['a', 'b']);
});
