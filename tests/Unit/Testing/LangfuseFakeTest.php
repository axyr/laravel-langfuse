<?php

declare(strict_types=1);

use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Dto\CreateDatasetBody;
use Axyr\Langfuse\Dto\CreateDatasetItemBody;
use Axyr\Langfuse\Dto\DatasetItemQuery;
use Axyr\Langfuse\Dto\DatasetItemResponse;
use Axyr\Langfuse\Dto\DatasetResponse;
use Axyr\Langfuse\Dto\EventBody;
use Axyr\Langfuse\Dto\ExperimentContext;
use Axyr\Langfuse\Dto\ExperimentItemContext;
use Axyr\Langfuse\Dto\ExperimentItemQuery;
use Axyr\Langfuse\Dto\ExperimentItemResponse;
use Axyr\Langfuse\Dto\ExperimentQuery;
use Axyr\Langfuse\Dto\ExperimentResponse;
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\IdGenerator;
use Axyr\Langfuse\Dto\MetricQuery;
use Axyr\Langfuse\Dto\ObservationQuery;
use Axyr\Langfuse\Dto\ObservationResponse;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Dto\ScoreQuery;
use Axyr\Langfuse\Dto\ScoreResponse;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Dto\TextPrompt;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Dto\Usage;
use Axyr\Langfuse\Exceptions\PromptNotFoundException;
use Axyr\Langfuse\Prompt\CurrentPromptRegistry;
use Axyr\Langfuse\Testing\LangfuseFake;
use Axyr\Langfuse\Tracing\CompletedObservation;
use Tests\Fixtures\ExperimentFixtures;
use Tests\Fixtures\ObservationFixtures;
use Tests\Fixtures\ScoreFixtures;

it('records traces as soon as they are created', function () {
    $fake = new LangfuseFake();

    $trace = $fake->trace(new TraceBody(id: 'trace-1', name: 'test'));

    expect($trace->getId())->toBe(IdGenerator::traceIdFromSeed('trace-1'));

    $fake->assertTraceCreated();
    $fake->assertTraceCreated('test');
});

it('separates created from ended traces', function () {
    $fake = new LangfuseFake();

    $trace = $fake->trace(new TraceBody(name: 'test'));

    $fake->assertTraceCreated('test');
    expect(fn() => $fake->assertTraceEnded('test'))->toThrow(\PHPUnit\Framework\AssertionFailedError::class);

    $trace->end();

    $fake->assertTraceEnded('test');
});

it('records scores', function () {
    $fake = new LangfuseFake();

    $fake->score(new ScoreBody(id: 'score-1', name: 'accuracy', value: 0.95));

    $fake->assertScoreCreated();
    $fake->assertScoreCreated('accuracy');

    expect($fake->scores())->toHaveCount(1);
});

it('records generations and their end', function () {
    $fake = new LangfuseFake();

    $trace = $fake->trace(new TraceBody(name: 'test'));
    $generation = $trace->generation(new GenerationBody(name: 'chat'));

    $fake->assertGenerationCreated();
    $fake->assertGenerationCreated('chat');

    $generation->end(output: 'done');

    $fake->assertGenerationEnded('chat');
});

it('records spans and their end', function () {
    $fake = new LangfuseFake();

    $trace = $fake->trace(new TraceBody());
    $span = $trace->span(new SpanBody(name: 'processing'));

    $fake->assertSpanCreated();
    $fake->assertSpanCreated('processing');

    $span->end();

    $fake->assertSpanEnded('processing');
});

it('asserts nothing sent', function () {
    (new LangfuseFake())->assertNothingSent();
});

it('fails assertNothingSent when a trace was created', function () {
    $fake = new LangfuseFake();
    $fake->trace(new TraceBody());

    expect(fn() => $fake->assertNothingSent())->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
});

it('counts only what a flush would send', function () {
    $fake = new LangfuseFake();

    $trace = $fake->trace(new TraceBody(name: 'test'));
    $span = $trace->span(new SpanBody(name: 'work'));

    // Nothing has ended yet, so nothing would be sent.
    $fake->assertEventCount(0);

    $span->end();
    $trace->end();

    $fake->assertEventCount(2);
});

it('reports enabled', function () {
    expect((new LangfuseFake())->isEnabled())->toBeTrue();
});

it('exposes the exported observations', function () {
    $fake = new LangfuseFake();

    $trace = $fake->trace(new TraceBody(name: 'test'));
    $trace->generation(new GenerationBody(name: 'chat'))->end(output: 'answer');
    $fake->score(new ScoreBody(name: 'quality'));

    expect($fake->observations())->toHaveCount(1)
        ->and($fake->observations()[0])->toBeInstanceOf(CompletedObservation::class)
        ->and($fake->observations()[0]->name())->toBe('chat')
        ->and($fake->scores())->toHaveCount(1);
});

it('asserts on the contents of an exported observation', function () {
    $fake = new LangfuseFake();

    $trace = $fake->trace(new TraceBody(name: 'test', userId: 'user-1'));
    $trace->generation(new GenerationBody(name: 'chat', model: 'gpt-4'))->end(output: 'answer');

    $fake->assertObservationHas('chat', function (CompletedObservation $observation): bool {
        return $observation->body->model === 'gpt-4'
            && $observation->context->body()->userId === 'user-1';
    });
});

it('builds the otlp payload a flush would post', function () {
    $fake = new LangfuseFake();

    $trace = $fake->trace(new TraceBody(name: 'test'));
    $trace->generation(new GenerationBody(name: 'chat'))->end(output: 'answer');
    $trace->end();

    $exported = $fake->exported();

    expect($exported)->toHaveCount(1)
        ->and($exported[0]['resourceSpans'][0]['scopeSpans'][0]['spans'])->toHaveCount(2);
});

it('exports nothing before anything ends', function () {
    $fake = new LangfuseFake();
    $fake->trace(new TraceBody(name: 'test'));

    expect($fake->exported())->toBe([]);
});

it('ends the open observations on shutdown', function () {
    $fake = new LangfuseFake();

    $trace = $fake->trace(new TraceBody(name: 'test'));
    $trace->span(new SpanBody(name: 'work'));

    $fake->shutdown();

    $fake->assertTraceEnded('test');
    $fake->assertSpanEnded('work');
    $fake->assertEventCount(2);
});

it('stamps the configured environment and trace context like the real client', function () {
    $fake = new LangfuseFake(
        new CurrentPromptRegistry(),
        new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', environment: 'staging'),
    );

    $trace = $fake->trace(new TraceBody(name: 'test'));

    expect($trace->getBody()->environment)->toBe('staging');
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
    $result = (new LangfuseFake())->prompt('unknown', fallback: 'Fallback {{var}}');

    expect($result->isFallback())->toBeTrue()
        ->and($result->compile(['var' => 'value']))->toBe('Fallback value');
});

it('throws when no prompt and no fallback', function () {
    (new LangfuseFake())->prompt('nonexistent');
})->throws(PromptNotFoundException::class);

it('records events', function () {
    $fake = new LangfuseFake();

    $trace = $fake->trace(new TraceBody());
    $trace->event(new EventBody(name: 'user-action'));

    $fake->assertEventCreated();
    $fake->assertEventCreated('user-action');
});

it('supports full trace flow', function () {
    $fake = new LangfuseFake();

    $trace = $fake->trace(new TraceBody(name: 'full-flow'));

    $span = $trace->span(new SpanBody(name: 'outer'));
    $generation = $span->generation(new GenerationBody(name: 'llm-call', model: 'gpt-4'));
    $generation->end(output: 'response', usage: new Usage(input: 10, output: 20));
    $span->end();

    $trace->score(new ScoreBody(id: 'score-1', name: 'quality', value: 0.9));
    $trace->event(new EventBody(name: 'user-action'));
    $trace->end(output: 'response');

    $fake->assertTraceCreated('full-flow')
        ->assertTraceEnded('full-flow')
        ->assertSpanCreated('outer')
        ->assertSpanEnded('outer')
        ->assertGenerationCreated('llm-call')
        ->assertGenerationEnded('llm-call')
        ->assertScoreCreated('quality')
        ->assertEventCreated('user-action');
});

it('chains assertions fluently', function () {
    $fake = new LangfuseFake();

    $trace = $fake->trace(new TraceBody(name: 'test'));
    $trace->generation(new GenerationBody(name: 'chat'));
    $fake->score(new ScoreBody(id: 'score-1', name: 'quality'));

    $fake->assertTraceCreated('test')
        ->assertGenerationCreated('chat')
        ->assertScoreCreated('quality');
});

it('registers configured prompts for generation linking', function () {
    $registry = new CurrentPromptRegistry();
    $fake = new LangfuseFake($registry);
    $prompt = new TextPrompt(name: 'movie-critic', version: 7, prompt: 'text');

    $fake->withPrompt($prompt);
    $fake->prompt('movie-critic');

    expect($registry->current())->toBe($prompt);
});

it('does not register fallback prompts for generation linking', function () {
    $registry = new CurrentPromptRegistry();
    $fake = new LangfuseFake($registry);

    $fake->prompt('unknown', fallback: 'Fallback');

    expect($registry->current())->toBeNull();
});

it('serves seeded score reads with cursor meta', function () {
    $fake = new LangfuseFake();
    $score = ScoreResponse::fromArray(ScoreFixtures::numericScore());

    $fake->withScore($score);

    expect($fake->getScore('score-abc'))->toBe($score)
        ->and($fake->getScore('missing'))->toBeNull()
        ->and($fake->getScores()->data)->toBe([$score])
        ->and($fake->getScores(new ScoreQuery(limit: 10))->meta->limit)->toBe(10)
        ->and($fake->getScores()->meta->cursor)->toBeNull();
});

it('records deleted scores', function () {
    $fake = new LangfuseFake();

    expect($fake->deleteScore('score-1'))->toBeTrue();

    $fake->assertScoreDeleted()->assertScoreDeleted('score-1');
});

it('serves seeded observation reads', function () {
    $fake = new LangfuseFake();
    $observation = ObservationResponse::fromArray(ObservationFixtures::generation());

    $fake->withObservation($observation);

    expect($fake->getObservation('0192f1b42c7e7a1b'))->toBe($observation)
        ->and($fake->getObservation('missing'))->toBeNull()
        ->and($fake->getObservations(new ObservationQuery())->data)->toBe([$observation]);
});

it('serves seeded metrics', function () {
    $fake = new LangfuseFake();

    $fake->withMetrics([['traceName' => 'chat', 'count_count' => 3]]);

    $result = $fake->queryMetrics(new MetricQuery(
        view: 'observations',
        metrics: [['measure' => 'count', 'aggregation' => 'count']],
        fromTimestamp: '2024-05-01T00:00:00Z',
        toTimestamp: '2024-05-02T00:00:00Z',
    ));

    expect($result->data)->toBe([['traceName' => 'chat', 'count_count' => 3]]);
});

it('serves seeded dataset reads and records creations', function () {
    $fake = new LangfuseFake();
    $dataset = DatasetResponse::fromArray(['id' => 'ds-1', 'name' => 'my-set']);
    $item = DatasetItemResponse::fromArray(['id' => 'item-1', 'datasetName' => 'my-set']);

    $fake->withDataset($dataset)->withDatasetItem($item);

    expect($fake->getDataset('my-set'))->toBe($dataset)
        ->and($fake->getDataset('missing'))->toBeNull()
        ->and($fake->listDatasets()->data)->toBe([$dataset])
        ->and($fake->getDatasetItem('item-1'))->toBe($item)
        ->and($fake->listDatasetItems(new DatasetItemQuery())->data)->toBe([$item])
        ->and($fake->deleteDatasetItem('item-1'))->toBeTrue();

    $fake->createDataset(new CreateDatasetBody(name: 'my-set'));
    $fake->createDatasetItem(new CreateDatasetItemBody(datasetName: 'my-set', input: 'q'));

    $fake->assertDatasetCreated('my-set')->assertDatasetItemCreated('my-set');
});

it('serves seeded experiment reads', function () {
    $fake = new LangfuseFake();
    $experiment = ExperimentResponse::fromArray(ExperimentFixtures::experiment());
    $item = ExperimentItemResponse::fromArray(ExperimentFixtures::experimentItem());

    $fake->withExperiment($experiment)->withExperimentItem($item);

    expect($fake->getExperiment('experiment-1', '2024-05-01T00:00:00Z'))->toBe($experiment)
        ->and($fake->getExperiment('missing', '2024-05-01T00:00:00Z'))->toBeNull()
        ->and($fake->listExperiments(new ExperimentQuery(fromStartTime: '2024-05-01T00:00:00Z'))->data)->toBe([$experiment])
        ->and($fake->listExperimentItems(new ExperimentItemQuery(fromStartTime: '2024-05-01T00:00:00Z'))->data)->toBe([$item]);
});

it('asserts that an experiment item was traced', function () {
    $fake = new LangfuseFake();

    $fake->trace((new TraceBody(name: 'eval', input: 'question'))->forExperimentItem(
        ExperimentContext::named('nightly-eval', datasetId: 'ds-1'),
        new ExperimentItemContext(itemId: 'item-1'),
    ));

    $fake->assertExperimentItemTraced()->assertExperimentItemTraced('nightly-eval');
});

it('fails the experiment item assertion when no item trace was created', function () {
    $fake = new LangfuseFake();
    $fake->trace(new TraceBody(name: 'plain'));

    expect(fn() => $fake->assertExperimentItemTraced())->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
});

it('records created prompts', function () {
    $fake = new LangfuseFake();

    $fake->createPrompt(new \Axyr\Langfuse\Dto\CreatePromptBody(
        name: 'movie-critic',
        type: 'text',
        prompt: 'text',
    ));

    $fake->assertPromptCreated('movie-critic');
});

it('lists prompts as an empty page', function () {
    expect((new LangfuseFake())->listPrompts()->data)->toBe([]);
});
