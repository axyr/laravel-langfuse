<?php

declare(strict_types=1);

use Axyr\Langfuse\Tracing\TraceUserId;
use Illuminate\Auth\GenericUser;

it('converts user identifiers to trace user ids', function (mixed $value, ?string $expected) {
    expect(TraceUserId::from($value))->toBe($expected);
})->with([
    'authenticatable' => [new GenericUser(['id' => 42]), '42'],
    'authenticatable with string key' => [new GenericUser(['id' => 'usr_1']), 'usr_1'],
    'object with getKey()' => [new class () {
        public function getKey(): int
        {
            return 9;
        }
    }, '9'],
    'object with id property' => [(object) ['id' => 'usr_9'], 'usr_9'],
    'stringable' => [new class () implements Stringable {
        public function __toString(): string
        {
            return 'usr_s';
        }
    }, 'usr_s'],
    'object without key' => [new stdClass(), null],
    'int' => [7, '7'],
    'string' => ['usr_7', 'usr_7'],
    'empty string' => ['', null],
    'null' => [null, null],
    'bool' => [true, null],
    'float' => [1.5, null],
]);
