# Opik PHP SDK

The official PHP SDK for [Opik](https://www.comet.com/docs/opik/) - an LLM observability and evaluation platform.

## Requirements

- PHP 8.4 or higher
- Composer

## Installation

```bash
composer require comet-ml/opik
```

## Quick Start

### Configuration

For **cloud usage** (recommended), set environment variables:

```bash
export OPIK_API_KEY=your-api-key
export OPIK_WORKSPACE=your-workspace  
export OPIK_PROJECT_NAME=your-project
```

```php
<?php

use Opik\OpikClient;

// Cloud configuration (recommended)
$client = new OpikClient();
```

Alternative configuration methods:

```php
// Constructor parameters (not recommended for production)
$client = new OpikClient(
    apiKey: 'your-api-key',
    workspace: 'your-workspace',
    projectName: 'my-project',
);

// Local development only (no auth required)
$client = new OpikClient(
    baseUrl: 'http://localhost:5173/api/',
);
```

### Basic Tracing

```php
<?php

use Opik\OpikClient;
use Opik\Tracer\SpanType;

$client = new OpikClient();

// Create a trace
$trace = $client->trace(
    name: 'chat-completion',
    input: ['messages' => [['role' => 'user', 'content' => 'Hello!']]],
);

// Create a span within the trace
$span = $trace->span(
    name: 'openai-call',
    type: SpanType::LLM,
    input: ['model' => 'gpt-4', 'messages' => [...]],
);

// Update span with output and usage
$span->update(
    output: ['response' => 'Hi there!'],
    model: 'gpt-4',
    provider: 'openai',
    usage: new \Opik\Tracer\Usage(
        promptTokens: 10,
        completionTokens: 5,
        totalTokens: 15,
    ),
);

// End the span and trace
$span->end();
$trace->update(output: ['response' => 'Hi there!']);
$trace->end();

// Flush all pending data
$client->flush();
```

### Nested Spans

```php
<?php

$trace = $client->trace(name: 'multi-step-process');

$parentSpan = $trace->span(name: 'process-request');

// Create child spans
$childSpan1 = $parentSpan->span(name: 'validate-input', type: SpanType::TOOL);
$childSpan1->update(output: ['valid' => true]);
$childSpan1->end();

$childSpan2 = $parentSpan->span(name: 'generate-response', type: SpanType::LLM);
$childSpan2->update(output: ['text' => 'Generated response']);
$childSpan2->end();

$parentSpan->end();
$trace->end();
```

### Feedback Scores

```php
<?php

$trace = $client->trace(name: 'scored-interaction');

// Log numerical feedback score
$trace->logFeedbackScore(
    name: 'relevance',
    value: 0.95,
    reason: 'Response directly addresses the question',
);

// Log categorical feedback score on a span
$span = $trace->span(name: 'llm-call', type: SpanType::LLM);
$span->logFeedbackScore(
    name: 'sentiment',
    value: 'positive',
    categoryName: 'positive',
);
```

### Datasets

```php
<?php

use Opik\Dataset\DatasetItem;
use Opik\Dataset\DatasetItemSource;

// Create or get a dataset
$dataset = $client->getOrCreateDataset(
    name: 'my-evaluation-dataset',
    description: 'Test cases for evaluation',
);

// Insert items with standard fields
$dataset->insert([
    new DatasetItem(
        input: ['question' => 'What is PHP?'],
        expectedOutput: ['answer' => 'A programming language'],
        metadata: ['difficulty' => 'easy'],
    ),
    new DatasetItem(
        input: ['question' => 'What is Opik?'],
        expectedOutput: ['answer' => 'An LLM observability platform'],
    ),
]);

// Insert items with flexible schema (arbitrary fields)
$dataset->insert([
    new DatasetItem(data: [
        'prompt' => 'Translate to French: Hello',
        'expected' => 'Bonjour',
        'language' => 'French',
    ]),
]);

// Insert items linked to traces/spans
$dataset->insert([
    new DatasetItem(
        input: ['query' => 'Example query'],
        traceId: 'trace-uuid',
        spanId: 'span-uuid',
        source: DatasetItemSource::TRACE,
    ),
]);

// Get items
$items = $dataset->getItems(page: 1, size: 100);

// Access item data
foreach ($items as $item) {
    $input = $item->getInput();           // Get input field
    $output = $item->getExpectedOutput(); // Get expected_output field
    $metadata = $item->getMetadata();     // Get metadata field
    $content = $item->getContent();       // Get all fields
    $custom = $item->get('custom_field'); // Get specific field
}
```

### Experiments

```php
<?php

use Opik\Experiment\ExperimentItem;

// Create an experiment
$experiment = $client->createExperiment(
    name: 'gpt-4-evaluation',
    datasetName: 'my-evaluation-dataset',
);

// Log experiment items
$experiment->logItems([
    new ExperimentItem(
        datasetItemId: 'item-id-1',
        traceId: 'trace-id-1',
        output: ['result' => 'Generated answer'],
        feedbackScores: [
            ['name' => 'accuracy', 'value' => 0.9],
        ],
    ),
]);
```

### Prompts

```php
<?php

// Create a prompt
$prompt = $client->createPrompt(
    name: 'greeting-prompt',
    template: 'Hello {{name}}, you asked: {{question}}',
    description: 'A greeting template',
);

// Get a prompt
$prompt = $client->getPrompt('my-prompt-template');

// Format with variables
$formatted = $prompt->format([
    'user_input' => 'Hello!',
    'context' => 'You are a helpful assistant.',
]);

// Get a specific version
$version = $prompt->getVersion('abc123');
$formatted = $version->format(['variable' => 'value']);

// Get prompt version history
$history = $client->getPromptHistory('my-prompt-template');

// Delete prompts in batch
$client->deletePrompts(['prompt-id-1', 'prompt-id-2']);
```

### Searching Traces and Spans

```php
<?php

// Search traces with filters
$results = $client->searchTraces(
    projectName: 'my-project',
    filter: 'name = "chat-completion"',
    page: 1,
    size: 50,
);

// Search spans
$spans = $client->searchSpans(
    traceId: 'trace-123',
    projectName: 'my-project',
    filter: 'type = "llm"',
);

// Get trace/span content by ID
$trace = $client->getTraceContent('trace-123');
$span = $client->getSpanContent('span-456');
```

### Batch Feedback Scores

```php
<?php

// Log feedback scores for multiple traces
$client->logTracesFeedbackScores([
    ['trace_id' => 'trace-1', 'name' => 'quality', 'value' => 0.9],
    ['trace_id' => 'trace-2', 'name' => 'quality', 'value' => 0.85],
]);

// Log feedback scores for multiple spans
$client->logSpansFeedbackScores([
    ['span_id' => 'span-1', 'name' => 'accuracy', 'value' => 0.95],
    ['span_id' => 'span-2', 'name' => 'accuracy', 'value' => 0.88],
]);

// Delete feedback scores
$client->deleteTraceFeedbackScore('trace-123', 'quality');
$client->deleteSpanFeedbackScore('span-456', 'accuracy');
```

### Thread Support

```php
<?php

// Create traces with thread ID for grouping conversations
$trace1 = $client->trace(
    name: 'user-message-1',
    threadId: 'conversation-123',
);

$trace2 = $client->trace(
    name: 'user-message-2',
    threadId: 'conversation-123',
);
```

### Project Management

```php
<?php

// Get project by ID
$project = $client->getProject('project-uuid');

// Get project URL (for linking in logs/UI)
$url = $client->getProjectUrl('my-project');
// Returns: https://www.comet.com/workspace/projects/my-project/traces
```

### Dataset Management

```php
<?php

// List all datasets
$datasets = $client->getDatasets(page: 1, size: 100);

// Delete a dataset
$client->deleteDataset('dataset-name');
```

### Experiment Management

```php
<?php

// Get experiment by ID
$experiment = $client->getExperimentById('experiment-uuid');

// Update experiment
$client->updateExperiment(
    id: 'experiment-uuid',
    name: 'updated-name',
    metadata: ['version' => '2.0'],
);

// Delete experiment
$client->deleteExperiment('experiment-name');
```

### Using the Track Handler

For automatic tracing of functions:

```php
<?php

use Opik\Decorator\TrackHandler;
use Opik\OpikClient;
use Opik\Tracer\SpanType;

$client = new OpikClient();
TrackHandler::setClient($client);

// Wrap a function with automatic tracing
$result = TrackHandler::track(
    callback: fn() => callLlm($prompt),
    name: 'llm-call',
    type: SpanType::LLM,
);
```

## Environment Variables

| Variable | Description | Required | Default |
|----------|-------------|----------|---------|
| `OPIK_API_KEY` | API key for authentication | Yes (cloud) | - |
| `OPIK_WORKSPACE` | Workspace name | Yes (cloud) | - |
| `OPIK_PROJECT_NAME` | Project name | No | `Default Project` |
| `OPIK_URL_OVERRIDE` | Custom API URL | No | - |
| `OPIK_DEBUG` | Enable debug mode | No | `false` |
| `OPIK_ENABLE_COMPRESSION` | Enable gzip request compression | No | `true` |

**For cloud usage**, you must set `OPIK_API_KEY` and `OPIK_WORKSPACE`. Get these from your [Opik dashboard](https://www.comet.com/docs/opik/).

## API Reference

### OpikClient

#### Tracing
- `trace(name, projectName?, id?, input?, metadata?, tags?, startTime?, threadId?)` - Create a trace
- `span(name, traceId, projectName?, parentSpanId?, type?, id?, input?, metadata?, tags?, startTime?)` - Create a span
- `searchTraces(projectName?, filter?, page?, size?)` - Search traces with OQL filter
- `searchSpans(traceId?, projectName?, filter?, page?, size?)` - Search spans with OQL filter
- `getTraceContent(traceId)` - Get trace by ID
- `getSpanContent(spanId)` - Get span by ID

#### Datasets
- `getDataset(name)` - Get a dataset by name
- `getDatasets(page?, size?)` - List all datasets with pagination
- `createDataset(name, description?)` - Create a new dataset
- `getOrCreateDataset(name, description?)` - Get or create a dataset
- `deleteDataset(name)` - Delete a dataset by name

#### Experiments
- `getExperiment(name)` - Get an experiment by name
- `getExperimentById(id)` - Get an experiment by ID
- `getExperiments(datasetId?, page?, size?)` - List all experiments with pagination
- `createExperiment(name, datasetName, datasetId?)` - Create a new experiment
- `updateExperiment(id, name?, metadata?)` - Update experiment metadata
- `deleteExperiment(name)` - Delete an experiment by name

#### Prompts
- `getPrompt(name)` - Get a prompt by name
- `getPrompts(page?, size?)` - List all prompts with pagination
- `createPrompt(name, template, description?, metadata?)` - Create a new prompt
- `getPromptHistory(name, page?, size?)` - Get all versions of a prompt
- `deletePrompts(ids)` - Delete prompts in batch

#### Feedback Scores
- `logTracesFeedbackScores(scores)` - Log feedback scores for multiple traces
- `logSpansFeedbackScores(scores)` - Log feedback scores for multiple spans
- `deleteTraceFeedbackScore(traceId, name)` - Delete a trace feedback score
- `deleteSpanFeedbackScore(spanId, name)` - Delete a span feedback score

#### Projects
- `getProject(id)` - Get project by ID
- `getProjectUrl(projectName?)` - Get URL to project in Opik UI

#### Utilities
- `flush()` - Flush all pending data to the server
- `getConfig()` - Get current configuration
- `getBatchQueue()` - Get batch queue (advanced usage)

### Trace

- `span(name, parentSpanId?, type?, input?, metadata?, tags?)` - Create a child span
- `update(input?, output?, metadata?, tags?, endTime?, errorInfo?)` - Update trace data
- `end(endTime?)` - End the trace
- `logFeedbackScore(name, value, reason?, categoryName?)` - Log a feedback score
- `getId()` - Get trace ID
- `getName()` - Get trace name
- `getProjectName()` - Get project name
- `getThreadId()` - Get thread ID

### Span

- `span(name, type?, input?, metadata?, tags?)` - Create a child span
- `update(input?, output?, metadata?, tags?, endTime?, model?, provider?, usage?, errorInfo?, totalCost?)` - Update span data
- `end(endTime?)` - End the span
- `logFeedbackScore(name, value, reason?, categoryName?)` - Log a feedback score
- `getId()` - Get span ID
- `getName()` - Get span name
- `getTraceId()` - Get parent trace ID
- `getType()` - Get span type

### SpanType Enum

- `SpanType::GENERAL` - General purpose span
- `SpanType::LLM` - LLM call span
- `SpanType::TOOL` - Tool/function call span
- `SpanType::GUARDRAIL` - Guardrail check span

### DatasetItem

- `getContent()` - Get all data fields as array
- `get(key)` - Get a specific field by key
- `getInput()` - Get the input field (convenience accessor)
- `getExpectedOutput()` - Get the expected_output field (convenience accessor)
- `getMetadata()` - Get the metadata field (convenience accessor)

Constructor parameters:

- `id?` - Custom ID (auto-generated if not provided)
- `input?` - Standard input field
- `expectedOutput?` - Standard expected output field
- `metadata?` - Standard metadata field
- `traceId?` - Link to a trace
- `spanId?` - Link to a span
- `source?` - Source type (DatasetItemSource enum)
- `data?` - Arbitrary data fields (flexible schema)

### DatasetItemSource Enum

- `DatasetItemSource::SDK` - Created via SDK (default)
- `DatasetItemSource::MANUAL` - Created manually
- `DatasetItemSource::TRACE` - Created from a trace
- `DatasetItemSource::SPAN` - Created from a span

## License

Apache-2.0
