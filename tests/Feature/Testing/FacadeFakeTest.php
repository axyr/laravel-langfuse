<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Dto\TextPrompt;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\LangfuseFacade;
use Axyr\Langfuse\Prompt\CurrentPromptRegistry;
use Axyr\Langfuse\Testing\LangfuseFake;

it('swaps facade with fake', function () {
    $fake = LangfuseFacade::fake();

    expect($fake)->toBeInstanceOf(LangfuseFake::class)
        ->and(LangfuseFacade::getFacadeRoot())->toBe($fake);
});

it('records traces via facade fake', function () {
    $fake = LangfuseFacade::fake();

    LangfuseFacade::trace(new TraceBody(id: 'trace-1', name: 'test'));

    $fake->assertTraceCreated('test');
});

it('records scores via facade fake', function () {
    $fake = LangfuseFacade::fake();

    LangfuseFacade::score(new ScoreBody(id: 'score-1', name: 'accuracy', value: 0.9));

    $fake->assertScoreCreated('accuracy');
});

it('records complete flow via facade fake', function () {
    $fake = LangfuseFacade::fake();

    $trace = LangfuseFacade::trace(new TraceBody(id: 'trace-1', name: 'flow'));
    $trace->generation(new GenerationBody(id: 'gen-1', name: 'llm'));

    $fake->assertTraceCreated('flow')
        ->assertGenerationCreated('llm');
});

it('asserts nothing sent via facade fake', function () {
    $fake = LangfuseFacade::fake();

    $fake->assertNothingSent();
});

it('fake is enabled by default', function () {
    LangfuseFacade::fake();

    expect(LangfuseFacade::isEnabled())->toBeTrue();
});

it('shares the container prompt registry so fake prompts link to generations', function () {
    $fake = LangfuseFacade::fake();
    $fake->withPrompt(new TextPrompt(name: 'movie-critic', version: 7, prompt: 'text'));

    LangfuseFacade::prompt('movie-critic');

    expect($this->app->make(CurrentPromptRegistry::class)->current()?->getName())->toBe('movie-critic');
});
