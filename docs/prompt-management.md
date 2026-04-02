[Back to documentation](README.md)

# Prompt Management

Fetch, cache, compile, create, and list prompts from your Langfuse project.

## Fetching and compiling

```php
use Axyr\Langfuse\LangfuseFacade as Langfuse;

// Fetch and compile a text prompt
$prompt = Langfuse::prompt('movie-critic');
$compiled = $prompt->compile(['movie' => 'Dune 2']);

// Fetch a specific version or label
$prompt = Langfuse::prompt('movie-critic', version: 3);
$prompt = Langfuse::prompt('movie-critic', label: 'production');

// Fallback for when the API is unavailable
$prompt = Langfuse::prompt('movie-critic', fallback: 'Review {{movie}} briefly.');

// Chat prompts work the same way
$prompt = Langfuse::prompt('chat-assistant', fallback: [
    ['role' => 'system', 'content' => 'You are a helpful assistant.'],
]);
$messages = $prompt->compile(['name' => 'World']);
```

## Caching

Prompts are cached in-memory with a configurable TTL (`LANGFUSE_PROMPT_CACHE_TTL`). When the cache expires, the SDK revalidates from the API. If the API is unavailable, the stale cached version is returned.

## Prompt metadata

```php
$prompt->getName();          // 'movie-critic'
$prompt->getVersion();       // 3
$prompt->getConfig();        // ['temperature' => 0.7, ...]
$prompt->getLabels();        // ['production', 'latest']
$prompt->isFallback();       // true if using a fallback prompt
```

## Linking prompts to generations

Link prompt metadata to generations for full traceability in the Langfuse UI:

```php
$prompt = Langfuse::prompt('movie-critic');
$generation = $trace->generation(new GenerationBody(
    name: 'review',
    model: 'gpt-4',
    promptName: $prompt->getName(),
    promptVersion: $prompt->getVersion(),
));
```

## Creating prompts

```php
use Axyr\Langfuse\Dto\CreatePromptBody;

$prompt = Langfuse::createPrompt(new CreatePromptBody(
    name: 'movie-critic',
    type: 'text',
    prompt: 'Review {{movie}} in one paragraph.',
    labels: ['staging'],
));
```

## Listing prompts

```php
$response = Langfuse::listPrompts(name: 'movie', label: 'production', page: 1, limit: 10);

foreach ($response->data as $item) {
    echo "{$item->name} v{$item->version} ({$item->type})\n";
}

echo "Total: {$response->meta->totalItems}";
```

---

Previous: [Scores](scores.md) | Next: [Prism Integration](integrations/prism.md)
