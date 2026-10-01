<?php

declare(strict_types=1);

use Axyr\Langfuse\Cache\PromptCache;
use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\DatasetApiClientInterface;
use Axyr\Langfuse\Contracts\DatasetItemApiClientInterface;
use Axyr\Langfuse\Contracts\EventBatcherInterface;
use Axyr\Langfuse\Contracts\ExperimentApiClientInterface;
use Axyr\Langfuse\Contracts\MetricsApiClientInterface;
use Axyr\Langfuse\Contracts\ObservationApiClientInterface;
use Axyr\Langfuse\Contracts\PromptApiClientInterface;
use Axyr\Langfuse\Contracts\ScoreApiClientInterface;
use Axyr\Langfuse\Contracts\TraceContextResolverInterface;
use Axyr\Langfuse\Dto\CursorMeta;
use Axyr\Langfuse\Dto\ExperimentItemListResponse;
use Axyr\Langfuse\Dto\ExperimentItemQuery;
use Axyr\Langfuse\Dto\ExperimentListResponse;
use Axyr\Langfuse\Dto\ExperimentQuery;
use Axyr\Langfuse\Dto\ExperimentResponse;
use Axyr\Langfuse\Dto\IdGenerator;
use Axyr\Langfuse\Dto\MetricQuery;
use Axyr\Langfuse\Dto\MetricsResponse;
use Axyr\Langfuse\Dto\ObservationListMeta;
use Axyr\Langfuse\Dto\ObservationListResponse;
use Axyr\Langfuse\Dto\ObservationQuery;
use Axyr\Langfuse\Dto\ObservationResponse;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Dto\ScoreListResponse;
use Axyr\Langfuse\Dto\ScoreQuery;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\LangfuseClient;
use Axyr\Langfuse\Objects\LangfuseTrace;
use Axyr\Langfuse\Objects\NullLangfuseTrace;
use Axyr\Langfuse\Objects\OpenObservationRegistry;
use Axyr\Langfuse\Prompt\CurrentPromptRegistry;
use Axyr\Langfuse\Prompt\PromptManager;
use Axyr\Langfuse\Testing\RecordingEventBatcher;
use Tests\Fixtures\ExperimentFixtures;
use Tests\Fixtures\ObservationFixtures;
use Tests\Fixtures\ScoreFixtures;

/**
 * @param array<string, mixed> $overrides
 */
function makeClient(EventBatcherInterface $batcher, ?LangfuseConfig $config = null, array $overrides = []): LangfuseClient
{
    $config ??= new LangfuseConfig(publicKey: 'pk', secretKey: 'sk');
    $promptApiClient = $overrides['promptApiClient'] ?? Mockery::mock(PromptApiClientInterface::class);

    return new LangfuseClient(
        $batcher,
        $config,
        new PromptManager($promptApiClient, new PromptCache()),
        $overrides['scoreApiClient'] ?? Mockery::mock(ScoreApiClientInterface::class),
        $promptApiClient,
        $overrides['observationApiClient'] ?? Mockery::mock(ObservationApiClientInterface::class),
        $overrides['metricsApiClient'] ?? Mockery::mock(MetricsApiClientInterface::class),
        $overrides['datasetApiClient'] ?? Mockery::mock(DatasetApiClientInterface::class),
        $overrides['datasetItemApiClient'] ?? Mockery::mock(DatasetItemApiClientInterface::class),
        $overrides['experimentApiClient'] ?? Mockery::mock(ExperimentApiClientInterface::class),
        $overrides['registry'] ?? new OpenObservationRegistry(),
        $overrides['promptRegistry'] ?? new CurrentPromptRegistry(),
        $overrides['contextResolver'] ?? new \Axyr\Langfuse\Tracing\NullTraceContextResolver(),
    );
}

it('creates a trace and returns LangfuseTrace without sending anything', function () {
    $batcher = new RecordingEventBatcher();
    $client = makeClient($batcher);

    $trace = $client->trace(new TraceBody(id: 'trace-1', name: 'test'));

    expect($trace)->toBeInstanceOf(LangfuseTrace::class)
        ->and($trace->getId())->toBe(IdGenerator::traceIdFromSeed('trace-1'))
        ->and($batcher->count())->toBe(0);
});

it('creates a trace with an auto-generated hex id', function () {
    $trace = makeClient(new RecordingEventBatcher())->trace(new TraceBody(name: 'test'));

    expect($trace->getId())->toMatch('/^[0-9a-f]{32}$/');
});

it('registers new traces so shutdown can end them', function () {
    $registry = new OpenObservationRegistry();
    $batcher = new RecordingEventBatcher();
    $client = makeClient($batcher, null, ['registry' => $registry]);

    $client->trace(new TraceBody(name: 'test'));

    expect($registry->count())->toBe(1);
});

it('enqueues a score, not an ingestion event', function () {
    $batcher = new RecordingEventBatcher();
    $client = makeClient($batcher);

    $client->score(new ScoreBody(name: 'accuracy', id: 'score-1', traceId: 'trace-1', value: 0.95));

    expect($batcher->observations())->toBeEmpty()
        ->and($batcher->scores())->toHaveCount(1)
        ->and($batcher->scores()[0]->name)->toBe('accuracy')
        ->and($batcher->scores()[0]->traceId)->toBe(IdGenerator::traceIdFromSeed('trace-1'));
});

it('passes the score envelope timestamp to the batcher', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('enqueueScore')
        ->once()
        ->with(Mockery::type(ScoreBody::class), '2024-01-01T00:00:00Z');

    makeClient($batcher)->score(new ScoreBody(name: 'accuracy'), '2024-01-01T00:00:00Z');
});

it('delegates flush to batcher', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('flush')->once();

    makeClient($batcher)->flush();
});

it('ends every open observation and flushes on shutdown', function () {
    $registry = new OpenObservationRegistry();
    $batcher = new RecordingEventBatcher();
    $client = makeClient($batcher, null, ['registry' => $registry]);

    $trace = $client->trace(new TraceBody(name: 'root'));
    $trace->span(new SpanBody(name: 'work'));

    $client->shutdown();

    expect($registry->count())->toBe(0)
        ->and($batcher->observations())->toHaveCount(2)
        ->and(array_map(fn($o): string => $o->name(), $batcher->observations()))->toBe(['work', 'root']);
});

it('forgets the observations it shut down, so a worker does not accumulate them', function () {
    $registry = new OpenObservationRegistry();
    $client = makeClient(new RecordingEventBatcher(), null, ['registry' => $registry]);

    $client->trace(new TraceBody(name: 'first job'));
    $client->shutdown();

    expect($registry->all())->toBeEmpty();

    $client->trace(new TraceBody(name: 'second job'));

    expect($registry->count())->toBe(1);
});

it('stores and returns current trace', function () {
    $client = makeClient(new RecordingEventBatcher());

    expect($client->currentTrace())->toBeInstanceOf(NullLangfuseTrace::class);

    $trace = $client->trace(new TraceBody(name: 'test'));
    $client->setCurrentTrace($trace);

    expect($client->currentTrace())->toBe($trace);
});

it('reports enabled state from config', function () {
    $batcher = new RecordingEventBatcher();

    expect(makeClient($batcher, new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', enabled: true))->isEnabled())->toBeTrue()
        ->and(makeClient($batcher, new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', enabled: false))->isEnabled())->toBeFalse();
});

it('stamps the configured environment on traces that lack one', function () {
    $client = makeClient(
        new RecordingEventBatcher(),
        new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', environment: 'production'),
    );

    expect($client->trace(new TraceBody(id: 'trace-1'))->getBody()->environment)->toBe('production');
});

it('keeps an explicit trace environment over the configured one', function () {
    $client = makeClient(
        new RecordingEventBatcher(),
        new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', environment: 'production'),
    );

    expect($client->trace(new TraceBody(id: 'trace-1', environment: 'staging'))->getBody()->environment)->toBe('staging');
});

it('leaves traces without environment when none is configured', function () {
    expect(makeClient(new RecordingEventBatcher())->trace(new TraceBody(id: 'trace-1'))->getBody()->environment)->toBeNull();
});

it('stamps the configured release on traces that lack one', function () {
    $client = makeClient(
        new RecordingEventBatcher(),
        new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', release: '1.2.3'),
    );

    expect($client->trace(new TraceBody(name: 'test'))->getBody()->release)->toBe('1.2.3')
        ->and($client->trace(new TraceBody(name: 'test', release: '9.9.9'))->getBody()->release)->toBe('9.9.9');
});

it('stamps the configured environment on scores', function () {
    $batcher = new RecordingEventBatcher();
    $client = makeClient($batcher, new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', environment: 'production'));

    $client->score(new ScoreBody(name: 'accuracy', value: 1.0));

    expect($batcher->scores()[0]->environment)->toBe('production');
});

it('registers resolved prompts for generation linking', function () {
    $registry = new CurrentPromptRegistry();
    $promptApiClient = Mockery::mock(PromptApiClientInterface::class);
    $promptApiClient->shouldReceive('get')->once()->andReturn([
        'name' => 'movie-critic',
        'version' => 4,
        'type' => 'text',
        'prompt' => 'from api',
    ]);

    $client = makeClient(new RecordingEventBatcher(), null, [
        'promptApiClient' => $promptApiClient,
        'promptRegistry' => $registry,
    ]);

    $client->prompt('movie-critic');

    expect($registry->current()?->getName())->toBe('movie-critic')
        ->and($registry->current()?->getVersion())->toBe(4);
});

it('does not register fallback prompts for generation linking', function () {
    $registry = new CurrentPromptRegistry();
    $promptApiClient = Mockery::mock(PromptApiClientInterface::class);
    $promptApiClient->shouldReceive('get')->once()->andReturnNull();

    $client = makeClient(new RecordingEventBatcher(), null, [
        'promptApiClient' => $promptApiClient,
        'promptRegistry' => $registry,
    ]);

    $client->prompt('movie-critic', fallback: 'fallback text');

    expect($registry->current())->toBeNull();
});

function makeContextResolver(?string $userId, ?string $sessionId): TraceContextResolverInterface
{
    return new class ($userId, $sessionId) implements TraceContextResolverInterface {
        public function __construct(private readonly ?string $userId, private readonly ?string $sessionId) {}

        public function resolveUserId(): ?string
        {
            return $this->userId;
        }

        public function resolveSessionId(): ?string
        {
            return $this->sessionId;
        }
    };
}

it('fills userId and sessionId from the context resolver', function () {
    $client = makeClient(new RecordingEventBatcher(), null, [
        'contextResolver' => makeContextResolver('42', 'session-1'),
    ]);

    $body = $client->trace(new TraceBody(id: 'trace-1'))->getBody();

    expect($body->userId)->toBe('42')
        ->and($body->sessionId)->toBe('session-1');
});

it('keeps explicit userId and sessionId over the resolver', function () {
    $client = makeClient(new RecordingEventBatcher(), null, [
        'contextResolver' => makeContextResolver('42', 'session-1'),
    ]);

    $body = $client->trace(new TraceBody(id: 'trace-1', userId: 'explicit-user', sessionId: 'explicit-session'))->getBody();

    expect($body->userId)->toBe('explicit-user')
        ->and($body->sessionId)->toBe('explicit-session');
});

it('ignores the resolver when user or session tracing is disabled', function () {
    $client = makeClient(
        new RecordingEventBatcher(),
        new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', userTracingEnabled: false, sessionTracingEnabled: false),
        ['contextResolver' => makeContextResolver('42', 'session-1')],
    );

    $body = $client->trace(new TraceBody(id: 'trace-1'))->getBody();

    expect($body->userId)->toBeNull()
        ->and($body->sessionId)->toBeNull();
});

it('leaves traces untouched without a resolver', function () {
    $body = makeClient(new RecordingEventBatcher())->trace(new TraceBody(id: 'trace-1'))->getBody();

    expect($body->userId)->toBeNull()
        ->and($body->sessionId)->toBeNull();
});

it('delegates the score reads to the score api client', function () {
    $score = \Axyr\Langfuse\Dto\ScoreResponse::fromArray(ScoreFixtures::numericScore());
    $list = ScoreListResponse::fromArray(ScoreFixtures::scoreList());
    $query = new ScoreQuery(limit: 5);

    $scoreApiClient = Mockery::mock(ScoreApiClientInterface::class);
    $scoreApiClient->shouldReceive('get')->once()->with('score-abc')->andReturn($score);
    $scoreApiClient->shouldReceive('getMany')->once()->with($query)->andReturn($list);
    $scoreApiClient->shouldReceive('delete')->once()->with('score-abc')->andReturnTrue();

    $client = makeClient(new RecordingEventBatcher(), null, ['scoreApiClient' => $scoreApiClient]);

    expect($client->getScore('score-abc'))->toBe($score)
        ->and($client->getScores($query))->toBe($list)
        ->and($client->deleteScore('score-abc'))->toBeTrue();
});

it('delegates the observation reads to the observation api client', function () {
    $observation = ObservationResponse::fromArray(ObservationFixtures::generation());
    $list = new ObservationListResponse([], new ObservationListMeta());
    $query = new ObservationQuery(limit: 5);

    $observationApiClient = Mockery::mock(ObservationApiClientInterface::class);
    $observationApiClient->shouldReceive('get')
        ->once()
        ->with('obs-1', '2024-05-01T00:00:00Z', '2024-05-02T00:00:00Z', 'core,io')
        ->andReturn($observation);
    $observationApiClient->shouldReceive('getMany')->once()->with($query)->andReturn($list);

    $client = makeClient(new RecordingEventBatcher(), null, ['observationApiClient' => $observationApiClient]);

    expect($client->getObservation('obs-1', '2024-05-01T00:00:00Z', '2024-05-02T00:00:00Z', 'core,io'))->toBe($observation)
        ->and($client->getObservations($query))->toBe($list);
});

it('delegates the metrics query to the metrics api client', function () {
    $query = new MetricQuery(
        view: 'observations',
        metrics: [['measure' => 'count', 'aggregation' => 'count']],
        fromTimestamp: '2024-05-01T00:00:00Z',
        toTimestamp: '2024-05-02T00:00:00Z',
    );
    $response = new MetricsResponse(data: []);

    $metricsApiClient = Mockery::mock(MetricsApiClientInterface::class);
    $metricsApiClient->shouldReceive('query')->once()->with($query)->andReturn($response);

    $client = makeClient(new RecordingEventBatcher(), null, ['metricsApiClient' => $metricsApiClient]);

    expect($client->queryMetrics($query))->toBe($response);
});

it('delegates the experiment reads to the experiment api client', function () {
    $experiment = ExperimentResponse::fromArray(ExperimentFixtures::experiment());
    $experiments = new ExperimentListResponse([$experiment], new CursorMeta());
    $items = new ExperimentItemListResponse([], new CursorMeta());
    $experimentQuery = new ExperimentQuery(fromStartTime: '2024-05-01T00:00:00Z');
    $itemQuery = new ExperimentItemQuery(fromStartTime: '2024-05-01T00:00:00Z');

    $experimentApiClient = Mockery::mock(ExperimentApiClientInterface::class);
    $experimentApiClient->shouldReceive('listExperiments')->once()->with($experimentQuery)->andReturn($experiments);
    $experimentApiClient->shouldReceive('listExperimentItems')->once()->with($itemQuery)->andReturn($items);
    $experimentApiClient->shouldReceive('getExperiment')
        ->once()
        ->with('experiment-1', '2024-05-01T00:00:00Z', null)
        ->andReturn($experiment);

    $client = makeClient(new RecordingEventBatcher(), null, ['experimentApiClient' => $experimentApiClient]);

    expect($client->listExperiments($experimentQuery))->toBe($experiments)
        ->and($client->listExperimentItems($itemQuery))->toBe($items)
        ->and($client->getExperiment('experiment-1', '2024-05-01T00:00:00Z'))->toBe($experiment);
});
