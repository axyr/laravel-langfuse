<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Testing;

use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\LangfuseClientInterface;
use Axyr\Langfuse\Contracts\PromptInterface;
use Axyr\Langfuse\Contracts\TraceContextResolverInterface;
use Axyr\Langfuse\Dto\CreatePromptBody;
use Axyr\Langfuse\Dto\PromptFactory;
use Axyr\Langfuse\Dto\PromptListResponse;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Exceptions\PromptNotFoundException;
use Axyr\Langfuse\Objects\LangfuseTrace;
use Axyr\Langfuse\Objects\NullLangfuseTrace;
use Axyr\Langfuse\Objects\OpenObservationRegistry;
use Axyr\Langfuse\Otlp\OtlpRequestFactory;
use Axyr\Langfuse\Prompt\CurrentPromptRegistry;
use Axyr\Langfuse\Testing\Concerns\AssertsTracing;
use Axyr\Langfuse\Testing\Concerns\FakesDatasets;
use Axyr\Langfuse\Testing\Concerns\FakesExperiments;
use Axyr\Langfuse\Testing\Concerns\FakesReads;
use Axyr\Langfuse\Tracing\NullTraceContextResolver;
use PHPUnit\Framework\Assert;

/**
 * Test double mirroring the full LangfuseClientInterface; its weighted method
 * count scales with the interface and is expected to be high.
 *
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 */
class LangfuseFake implements LangfuseClientInterface
{
    use AssertsTracing;
    use FakesDatasets;
    use FakesExperiments;
    use FakesReads;

    private readonly RecordingEventBatcher $batcher;

    private readonly OpenObservationRegistry $observationRegistry;

    private LangfuseTrace $currentTrace;

    /** @var array<PromptInterface> */
    private array $promptResponses = [];

    /** @var array<CreatePromptBody> */
    private array $createdPrompts = [];

    public function __construct(
        private readonly CurrentPromptRegistry $promptRegistry = new CurrentPromptRegistry(),
        private readonly ?LangfuseConfig $config = null,
        private readonly TraceContextResolverInterface $contextResolver = new NullTraceContextResolver(),
    ) {
        $this->batcher = new RecordingEventBatcher();
        $this->observationRegistry = new OpenObservationRegistry();
        $this->currentTrace = new NullLangfuseTrace();
    }

    public function recorder(): RecordingEventBatcher
    {
        return $this->batcher;
    }

    public function registry(): OpenObservationRegistry
    {
        return $this->observationRegistry;
    }

    public function trace(TraceBody $body): LangfuseTrace
    {
        return new LangfuseTrace(
            body: $this->applyTraceContext(
                $body->withEnvironment($this->config?->environment)->withRelease($this->config?->release),
            ),
            batcher: $this->batcher,
            registry: $this->observationRegistry,
        );
    }

    /**
     * Mirrors LangfuseClient: the resolver fills in userId and sessionId when the
     * body does not set them.
     */
    private function applyTraceContext(TraceBody $body): TraceBody
    {
        $userTracing = $this->config->userTracingEnabled ?? true;
        $sessionTracing = $this->config->sessionTracingEnabled ?? true;

        return $body
            ->withUserId($userTracing ? $this->contextResolver->resolveUserId() : null)
            ->withSessionId($sessionTracing ? $this->contextResolver->resolveSessionId() : null);
    }

    public function currentTrace(): LangfuseTrace
    {
        return $this->currentTrace;
    }

    public function setCurrentTrace(LangfuseTrace $trace): void
    {
        $this->currentTrace = $trace;
    }

    public function score(ScoreBody $body, ?string $timestamp = null): void
    {
        $this->batcher->enqueueScore($body->withEnvironment($this->config?->environment), $timestamp);
    }

    /**
     * The OTLP request bodies a flush would post, for payload-level assertions.
     *
     * @return array<int, array<string, mixed>>
     */
    public function exported(): array
    {
        $factory = new OtlpRequestFactory($this->config ?? new LangfuseConfig(publicKey: 'pk-fake', secretKey: 'sk-fake'));

        return array_map(
            fn(\Axyr\Langfuse\Dto\Otlp\OtlpExportRequest $request): array => $request->toArray(),
            $factory->build($this->batcher->observations()),
        );
    }

    public function flush(): void
    {
        $this->batcher->flush();
    }

    public function shutdown(): void
    {
        $this->observationRegistry->endAll();
        $this->batcher->flush();
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function prompt(
        string $name,
        ?int $version = null,
        ?string $label = null,
        string|array|null $fallback = null,
    ): PromptInterface {
        if (isset($this->promptResponses[$name])) {
            $this->promptRegistry->set($this->promptResponses[$name]);

            return $this->promptResponses[$name];
        }

        if (is_string($fallback)) {
            return PromptFactory::fallbackText($name, $fallback);
        }

        if (is_array($fallback)) {
            return PromptFactory::fallbackChat($name, $fallback);
        }

        throw PromptNotFoundException::forName($name);
    }

    public function createPrompt(CreatePromptBody $body): ?PromptInterface
    {
        $this->createdPrompts[] = $body;

        return PromptFactory::fromApiResponse($body->toArray());
    }

    public function listPrompts(?string $name = null, ?string $label = null, ?int $page = null, ?int $limit = null): ?PromptListResponse
    {
        return PromptListResponse::fromArray([
            'data' => [],
            'meta' => ['totalItems' => 0, 'totalPages' => 0, 'page' => $page ?? 1, 'limit' => $limit ?? 10],
        ]);
    }

    public function withPrompt(PromptInterface $prompt): self
    {
        $this->promptResponses[$prompt->getName()] = $prompt;

        return $this;
    }

    public function assertPromptCreated(?string $name = null): self
    {
        Assert::assertNotEmpty($this->createdPrompts, 'Expected at least one prompt to be created, but none were.');

        if ($name !== null) {
            $names = array_map(fn(CreatePromptBody $p): string => $p->name, $this->createdPrompts);
            Assert::assertContains($name, $names, "Expected a prompt named '{$name}' to be created but it was not.");
        }

        return $this;
    }
}
