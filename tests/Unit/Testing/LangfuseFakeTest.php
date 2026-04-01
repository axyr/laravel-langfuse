<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\EventBody;
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Dto\TextPrompt;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Dto\Usage;
use Axyr\Langfuse\Exceptions\PromptNotFoundException;
use Axyr\Langfuse\Testing\LangfuseFake;

it('records traces', function () {
    $fake = new LangfuseFake();

    $trace = $fake->trace(new TraceBody(id: 'trace-1', name: 'test'));

    expect($trace->getId())->toBe('trace-1');
    $fake->assertTraceCreated();
    $fake->assertTraceCreated('test');
});

it('records scores', function () {
    $fake = new LangfuseFake();

    $fake->score(new ScoreBody(id: 'score-1', name: 'accuracy', value: 0.95));

    $fake->assertScoreCreated();
    $fake->assertScoreCreated('accuracy');
});

it('records generations', function () {
    $fake = new LangfuseFake();

    $trace = $fake->trace(new TraceBody(id: 'trace-1', name: 'test'));
    $trace->generation(new GenerationBody(id: 'gen-1', name: 'chat'));

    $fake->assertGenerationCreated();
    $fake->assertGenerationCreated('chat');
});

it('records spans', function () {
    $fake = new LangfuseFake();

    $trace = $fake->trace(new TraceBody(id: 'trace-1'));
    $trace->span(new SpanBody(id: 'span-1', name: 'processing'));

    $fake->assertSpanCreated();
    $fake->assertSpanCreated('processing');
});

it('asserts nothing sent', function () {
    $fake = new LangfuseFake();

    $fake->assertNothingSent();
});

it('fails assertNothingSent when events exist', function () {
    $fake = new LangfuseFake();
    $fake->trace(new TraceBody(id: 'trace-1'));

    expect(fn() => $fake->assertNothingSent())->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
});

it('asserts event count', function () {
    $fake = new LangfuseFake();

    $trace = $fake->trace(new TraceBody(id: 'trace-1'));
    $trace->span(new SpanBody(id: 'span-1'));

    // trace-create + span-create = 2 events
    $fake->assertEventCount(2);
});

it('reports enabled', function () {
    $fake = new LangfuseFake();

    expect($fake->isEnabled())->toBeTrue();
});

it('returns all recorded events', function () {
    $fake = new LangfuseFake();

    $trace = $fake->trace(new TraceBody(id: 'trace-1'));
    $trace->generation(new GenerationBody(id: 'gen-1', name: 'chat'));
    $fake->score(new ScoreBody(id: 'score-1', name: 'quality'));

    // trace-create + generation-create + score-create = 3
    expect($fake->events())->toHaveCount(3);
});

it('returns configured prompt', function () {
    $fake = new LangfuseFake();
    $prompt = new TextPrompt(name: 'test', version: 1, prompt: 'Hello {{name}}');

    $fake->withPrompt($prompt);

    $result = $fake->prompt('test');

    expect($result)->toBe($prompt)
        ->and($result->compile(['name' => 'World']))->toBe('Hello World');
});

it('returns fallback prompt when not configured', function () {
    $fake = new LangfuseFake();

    $result = $fake->prompt('unknown', fallback: 'Fallback {{var}}');

    expect($result->isFallback())->toBeTrue()
        ->and($result->compile(['var' => 'value']))->toBe('Fallback value');
});

it('throws when no prompt and no fallback', function () {
    $fake = new LangfuseFake();

    $fake->prompt('nonexistent');
})->throws(PromptNotFoundException::class);

it('records events', function () {
    $fake = new LangfuseFake();

    $trace = $fake->trace(new TraceBody(id: 'trace-1'));
    $trace->event(new EventBody(id: 'event-1', name: 'user-action'));

    $fake->assertEventCreated();
    $fake->assertEventCreated('user-action');
});

it('supports full trace flow', function () {
    $fake = new LangfuseFake();

    $trace = $fake->trace(new TraceBody(id: 'trace-1', name: 'full-flow'));

    $span = $trace->span(new SpanBody(id: 'span-1', name: 'outer'));
    $generation = $span->generation(new GenerationBody(
        id: 'gen-1',
        name: 'llm-call',
        model: 'gpt-4',
    ));
    $generation->end(output: 'response', usage: new Usage(input: 10, output: 20));
    $span->end();

    $trace->score(new ScoreBody(id: 'score-1', name: 'quality', value: 0.9));
    $trace->event(new EventBody(id: 'event-1', name: 'user-action'));

    $fake->assertTraceCreated('full-flow');
    $fake->assertSpanCreated('outer');
    $fake->assertGenerationCreated('llm-call');
    $fake->assertScoreCreated('quality');
    $fake->assertEventCreated('user-action');
});

it('chains assertions fluently', function () {
    $fake = new LangfuseFake();

    $trace = $fake->trace(new TraceBody(id: 'trace-1', name: 'test'));
    $trace->generation(new GenerationBody(id: 'gen-1', name: 'chat'));
    $fake->score(new ScoreBody(id: 'score-1', name: 'quality'));

    $fake->assertTraceCreated('test')
        ->assertGenerationCreated('chat')
        ->assertScoreCreated('quality');
});
