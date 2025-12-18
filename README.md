# Opik PHP SDK

> PHP SDK for [Opik](https://www.comet.com/docs/opik/) - an LLM observability and evaluation platform.

**NOTE**: This is a community-maintained SDK, not an official Comet ML product. For official SDKs, see [Python](https://github.com/comet-ml/opik/tree/main/sdks/python) and [TypeScript](https://github.com/comet-ml/opik/tree/main/sdks/typescript).

## Table of Contents

- [SDK Comparison](#sdk-comparison)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [Features](#features)
  - [Tracing](#tracing)
  - [Feedback Scores](#feedback-scores)
  - [Threads](#threads)
  - [Datasets](#datasets)
  - [Experiments](#experiments)
  - [Prompts](#prompts)
  - [Attachments](#attachments)
- [API Reference](#api-reference)
- [Development](#development)

---

## SDK Comparison

This table compares feature coverage between the official SDKs and this community PHP SDK.

| Category | Feature | Python | TypeScript | PHP | Notes |
|----------|---------|:------:|:----------:|:---:|-------|
| **Tracing** | Traces & Spans | :white_check_mark: | :white_check_mark: | :white_check_mark: | Full support |
| | Nested Spans | :white_check_mark: | :white_check_mark: | :white_check_mark: | Full support |
| | Search (OQL) | :white_check_mark: | :white_check_mark: | :white_check_mark: | Full support |
| | Span Types | :white_check_mark: | :white_check_mark: | :white_check_mark: | Full support |
| | Usage Tracking | :white_check_mark: | :white_check_mark: | :white_check_mark: | Full support |
| | Cost Calculation | :white_check_mark: | :white_check_mark: | :x: | Not implemented |
| | `@track` Decorator | :white_check_mark: | :white_check_mark: | :x: | PHP lacks decorators |
| **Feedback** | Feedback Scores | :white_check_mark: | :white_check_mark: | :white_check_mark: | Full support |
| | Batch Feedback | :white_check_mark: | :white_check_mark: | :white_check_mark: | Full support |
| | Threads | :white_check_mark: | :x: | :white_check_mark: | Full support |
| **Datasets** | CRUD Operations | :white_check_mark: | :white_check_mark: | :white_check_mark: | Full support |
| | Flexible Schema | :white_check_mark: | :white_check_mark: | :white_check_mark: | Full support |
| | JSON Import/Export | :white_check_mark: | :white_check_mark: | :white_check_mark: | Full support |
| **Experiments** | Create & Manage | :white_check_mark: | :white_check_mark: | :white_check_mark: | Full support |
| | Log Items | :white_check_mark: | :white_check_mark: | :white_check_mark: | Full support |
| **Prompts** | Text Prompts | :white_check_mark: | :white_check_mark: | :white_check_mark: | Full support |
| | Chat Prompts | :white_check_mark: | :white_check_mark: | :white_check_mark: | Full support |
| | Version History | :white_check_mark: | :white_check_mark: | :white_check_mark: | Full support |
| **Attachments** | Upload/Download | :white_check_mark: | :x: | :white_check_mark: | Full support |
| **Evaluation** | Heuristic Metrics | :white_check_mark: | :white_check_mark: | :white_check_mark: | ExactMatch, Contains, RegexMatch, IsJson |
| | LLM Judge Metrics | :white_check_mark: | :white_check_mark: | :x: | Not implemented |
| | `evaluate()` | :white_check_mark: | :white_check_mark: | :x: | Not implemented |
| **Integrations** | OpenAI | :white_check_mark: | :white_check_mark: | :x: | Not implemented |
| | LangChain | :white_check_mark: | :white_check_mark: | :x: | Not implemented |
| | Other Frameworks | :white_check_mark: | :white_check_mark: | :x: | Not implemented |
| **Advanced** | Guardrails | :white_check_mark: | :x: | :x: | Not implemented |
| | Simulation | :white_check_mark: | :x: | :x: | Not implemented |
| | CLI Commands | :white_check_mark: | :x: | :x: | Not implemented |

### Coverage Summary

| SDK | Core Features | Advanced Features | Overall |
|-----|:-------------:|:-----------------:|:-------:|
| **Python** (Official) | 100% | 100% | 100% |
| **TypeScript** (Official) | ~90% | ~60% | ~80% |
| **PHP** (Community) | ~90% | ~20% | **~65%** |

### What's Missing in PHP SDK

**High Priority (Core Functionality):**

- Evaluation framework (`evaluate()` function with metrics)
- Cost calculation for LLM calls

**Medium Priority (Integrations):**

- OpenAI integration for automatic tracing
- Other LLM provider integrations

**Low Priority (Advanced):**

- Guardrails (PII detection, topic filtering)
- Simulation framework
- CLI commands
- Local recording for testing

### Contributing

Contributions are welcome! If you'd like to help implement missing features, please see the [Development](#development) section.

---

## Installation

**Requirements:** PHP 8.1+, Composer

```bash
composer require klipitkas/opik-php
```

---

## Quick Start

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

// Create an LLM span within the trace
$span = $trace->span(name: 'openai-call', type: SpanType::LLM);
$span->update(
    output: ['response' => 'Hi there!'],
    model: 'gpt-4',
    provider: 'openai',
    usage: new \Opik\Tracer\Usage(promptTokens: 10, completionTokens: 5, totalTokens: 15),
);
$span->end();

// End trace and flush
$trace->update(output: ['response' => 'Hi there!']);
$trace->end();
$client->flush();
```

---

## Configuration

### Environment Variables

| Variable | Description | Required | Default |
|----------|-------------|----------|---------|
| `OPIK_API_KEY` | API key | Yes (cloud) | - |
| `OPIK_WORKSPACE` | Workspace name | Yes (cloud) | - |
| `OPIK_PROJECT_NAME` | Project name | No | `Default Project` |
| `OPIK_URL_OVERRIDE` | Custom API URL | No | - |
| `OPIK_DEBUG` | Enable debug mode | No | `false` |
| `OPIK_ENABLE_COMPRESSION` | Enable gzip compression | No | `true` |

### Setup Methods

```bash
# Cloud (recommended)
export OPIK_API_KEY=your-api-key
export OPIK_WORKSPACE=your-workspace
export OPIK_PROJECT_NAME=your-project-name
```

```php
// From environment (recommended)
$client = new OpikClient();

// Explicit parameters
$client = new OpikClient(
    apiKey: 'your-api-key',
    workspace: 'your-workspace',
    projectName: 'my-project',
);

// Local development
$client = new OpikClient(baseUrl: 'http://localhost:5173/api/');

// Verify credentials
if ($client->authCheck()) {
    echo "Connected!";
}
```

---

## Features

### Tracing

#### Basic Trace with Spans

```php
$trace = $client->trace(name: 'my-trace', input: ['query' => 'Hello']);

$span = $trace->span(name: 'process', type: SpanType::LLM);
$span->update(output: ['result' => 'Done']);
$span->end();

$trace->end();
$client->flush();
```

#### Nested Spans

```php
$trace = $client->trace(name: 'multi-step');
$parent = $trace->span(name: 'parent');

$child1 = $parent->span(name: 'step-1', type: SpanType::TOOL);
$child1->end();

$child2 = $parent->span(name: 'step-2', type: SpanType::LLM);
$child2->end();

$parent->end();
$trace->end();
```

#### Search Traces and Spans

```php
// Search traces with OQL filter
$traces = $client->searchTraces(
    projectName: 'my-project',
    filter: 'name = "chat-completion"',
);

// Get specific trace/span
$trace = $client->getTraceContent('trace-id');
$span = $client->getSpanContent('span-id');
```

#### Span Types

| Type | Description |
|------|-------------|
| `SpanType::GENERAL` | General purpose span |
| `SpanType::LLM` | LLM API call |
| `SpanType::TOOL` | Tool/function call |
| `SpanType::GUARDRAIL` | Guardrail check |

---

### Feedback Scores

#### On Traces and Spans

```php
$trace = $client->trace(name: 'scored-trace');

// Numeric score
$trace->logFeedbackScore(name: 'relevance', value: 0.95, reason: 'Good answer');

// Categorical score
$span = $trace->span(name: 'llm-call', type: SpanType::LLM);
$span->logFeedbackScore(name: 'sentiment', value: 1.0, categoryName: 'positive');
```

#### Batch Feedback Scores

```php
use Opik\Feedback\FeedbackScore;

// For traces
$client->logTracesFeedbackScores([
    FeedbackScore::forTrace('trace-1', 'quality', value: 0.9),
    FeedbackScore::forTrace('trace-2', 'quality', value: 0.85, reason: 'Good'),
]);

// For spans
$client->logSpansFeedbackScores([
    FeedbackScore::forSpan('span-1', 'accuracy', value: 0.95),
    FeedbackScore::forSpan('span-2', 'accuracy', categoryName: 'high'),
]);

// Delete feedback scores
$client->deleteTraceFeedbackScore('trace-id', 'quality');
$client->deleteSpanFeedbackScore('span-id', 'accuracy');
```

---

### Threads

Group related traces into conversations:

```php
use Opik\Feedback\FeedbackScore;

// Create traces in a thread
$trace1 = $client->trace(name: 'user-msg-1', threadId: 'conversation-123');
$trace1->end();

$trace2 = $client->trace(name: 'user-msg-2', threadId: 'conversation-123');
$trace2->end();
$client->flush();

// Close thread before scoring
$client->closeThread('conversation-123');

// Score the thread
$client->logThreadsFeedbackScores([
    FeedbackScore::forThread('conversation-123', 'satisfaction', value: 0.95),
]);
```

---

### Datasets

#### Create and Populate

```php
use Opik\Dataset\DatasetItem;

$dataset = $client->getOrCreateDataset(
    name: 'eval-dataset',
    description: 'Test cases',
);

// Standard schema
$dataset->insert([
    new DatasetItem(
        input: ['question' => 'What is PHP?'],
        expectedOutput: ['answer' => 'A programming language'],
        metadata: ['difficulty' => 'easy'],
    ),
]);

// Flexible schema
$dataset->insert([
    new DatasetItem(data: [
        'prompt' => 'Translate: Hello',
        'expected' => 'Bonjour',
    ]),
]);
```

#### Read and Manage

```php
// Get items
$items = $dataset->getItems(page: 1, size: 100);
foreach ($items as $item) {
    $input = $item->getInput();
    $output = $item->getExpectedOutput();
}

// Update/delete
$dataset->update($items);
$dataset->delete(['item-id-1', 'item-id-2']);
$dataset->clear(); // Delete all

// List/delete datasets
$datasets = $client->getDatasets();
$client->deleteDataset('dataset-name');
```

#### JSON Import/Export

```php
// Import from JSON string
$json = '[{"input": "question 1", "output": "answer 1"}, {"input": "question 2", "output": "answer 2"}]';
$dataset->insertFromJson($json);

// Import with key mapping (rename keys)
$json = '[{"Question": "What is PHP?", "Expected Answer": "A language"}]';
$dataset->insertFromJson($json, keysMapping: [
    'Question' => 'input',
    'Expected Answer' => 'expected_output',
]);

// Import while ignoring certain keys
$dataset->insertFromJson($json, ignoreKeys: ['internal_id', 'debug_info']);

// Export to JSON string
$json = $dataset->toJson();

// Export with key mapping
$json = $dataset->toJson(keysMapping: [
    'input' => 'Question',
    'expected_output' => 'Expected Answer',
]);
```

---

### Experiments

```php
use Opik\Experiment\ExperimentItem;

// Create experiment
$experiment = $client->createExperiment(
    name: 'gpt-4-eval',
    datasetName: 'eval-dataset',
);

// Log results
$experiment->logItems([
    new ExperimentItem(
        datasetItemId: 'item-1',
        traceId: 'trace-1',
        output: ['result' => 'Answer'],
        feedbackScores: [['name' => 'accuracy', 'value' => 0.9]],
    ),
]);

// Manage experiments
$experiment = $client->getExperimentById('experiment-id');
$client->updateExperiment(id: 'experiment-id', name: 'new-name');
$client->deleteExperiment('experiment-name');
```

---

### Prompts

Opik supports two types of prompts: **text prompts** (simple string templates) and **chat prompts** (array of messages following OpenAI's chat format).

#### Text Prompts

```php
// Create a text prompt
$prompt = $client->createPrompt(
    name: 'greeting',
    template: 'Hello {{name}}, you asked: {{question}}',
);

// Get and format
$prompt = $client->getPrompt('greeting');
$text = $prompt->format(['name' => 'John', 'question' => 'How are you?']);
// Returns: "Hello John, you asked: How are you?"
```

#### Chat Prompts

```php
use Opik\Prompt\ChatMessage;

// Create a chat prompt with messages array
$prompt = $client->createPrompt(
    name: 'assistant-prompt',
    template: [
        ChatMessage::system('You are a helpful assistant specializing in {{domain}}.'),
        ChatMessage::user('{{question}}'),
    ],
);

// Format returns array of messages
$messages = $prompt->format(['domain' => 'physics', 'question' => 'What is gravity?']);
// Returns:
// [
//     ['role' => 'system', 'content' => 'You are a helpful assistant specializing in physics.'],
//     ['role' => 'user', 'content' => 'What is gravity?'],
// ]
```

#### ChatMessage Factory Methods

| Method | Description |
|--------|-------------|
| `ChatMessage::system($content)` | Create a system message |
| `ChatMessage::user($content)` | Create a user message |
| `ChatMessage::assistant($content)` | Create an assistant message |
| `ChatMessage::tool($content)` | Create a tool message |

#### Prompt Versions

```php
// Get version history
$history = $client->getPromptHistory('greeting');

// Get specific version
$version = $prompt->getVersion('commit-hash');

// Check prompt type
if ($version->isChat()) {
    $messages = $version->format($variables);
} else {
    $text = $version->format($variables);
}
```

#### Delete Prompts

```php
$client->deletePrompts(['prompt-id-1', 'prompt-id-2']);
```

---

### Attachments

Upload files to traces or spans:

```php
use Opik\Attachment\AttachmentEntityType;

$attachmentClient = $client->getAttachmentClient();

// Upload
$attachmentClient->uploadAttachment(
    projectName: 'my-project',
    entityType: AttachmentEntityType::TRACE,
    entityId: $trace->getId(),
    filePath: '/path/to/file.pdf',
);

// List
$attachments = $attachmentClient->getAttachmentList(
    projectName: 'my-project',
    entityType: AttachmentEntityType::TRACE,
    entityId: $trace->getId(),
);

// Download
$content = $attachmentClient->downloadAttachment(
    projectName: 'my-project',
    entityType: AttachmentEntityType::TRACE,
    entityId: $trace->getId(),
    fileName: 'file.pdf',
    mimeType: 'application/pdf',
);
```

---

### Evaluation Metrics

The SDK provides heuristic metrics for evaluating LLM outputs:

```php
use Opik\Evaluation\Metrics\ExactMatch;
use Opik\Evaluation\Metrics\Contains;
use Opik\Evaluation\Metrics\RegexMatch;
use Opik\Evaluation\Metrics\IsJson;

// ExactMatch - checks for exact equality
$metric = new ExactMatch();
$result = $metric->score([
    'output' => 'hello world',
    'expected' => 'hello world',
]);
echo $result->value; // 1.0 (match) or 0.0 (no match)

// Contains - checks if output contains expected substring
$metric = new Contains(caseSensitive: false);
$result = $metric->score([
    'output' => 'Hello World',
    'expected' => 'hello',
]);
echo $result->value; // 1.0

// RegexMatch - checks if output matches a regex pattern
$metric = new RegexMatch();
$result = $metric->score([
    'output' => 'Contact: test@example.com',
    'pattern' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
]);
echo $result->value; // 1.0

// IsJson - checks if output is valid JSON
$metric = new IsJson();
$result = $metric->score([
    'output' => '{"key": "value"}',
]);
echo $result->value; // 1.0
```

#### Available Metrics

| Metric | Description |
|--------|-------------|
| `ExactMatch` | Checks if output exactly equals expected (strict comparison) |
| `Contains` | Checks if output contains expected substring (supports case-insensitive) |
| `RegexMatch` | Checks if output matches a regex pattern |
| `IsJson` | Checks if output is valid JSON |

---

## API Reference

### OpikClient Methods

| Category | Method | Description |
|----------|--------|-------------|
| **Tracing** | `trace(...)` | Create a trace |
| | `span(...)` | Create a standalone span |
| | `searchTraces(...)` | Search traces with OQL |
| | `searchSpans(...)` | Search spans with OQL |
| | `getTraceContent(id)` | Get trace by ID |
| | `getSpanContent(id)` | Get span by ID |
| **Feedback** | `logTracesFeedbackScores(scores)` | Batch log trace scores |
| | `logSpansFeedbackScores(scores)` | Batch log span scores |
| | `logThreadsFeedbackScores(scores)` | Batch log thread scores |
| | `deleteTraceFeedbackScore(id, name)` | Delete trace score |
| | `deleteSpanFeedbackScore(id, name)` | Delete span score |
| **Threads** | `closeThread(id)` | Close a thread |
| | `closeThreads(ids)` | Close multiple threads |
| **Datasets** | `getDataset(name)` | Get dataset |
| | `getDatasets()` | List datasets |
| | `createDataset(name)` | Create dataset |
| | `getOrCreateDataset(name)` | Get or create dataset |
| | `deleteDataset(name)` | Delete dataset |
| **Experiments** | `createExperiment(name, datasetName)` | Create experiment |
| | `getExperiment(name)` | Get by name |
| | `getExperimentById(id)` | Get by ID |
| | `updateExperiment(id, ...)` | Update experiment |
| | `deleteExperiment(name)` | Delete experiment |
| **Prompts** | `createPrompt(name, template)` | Create text or chat prompt |
| | `getPrompt(name)` | Get prompt |
| | `getPrompts()` | List prompts |
| | `getPromptHistory(name)` | Get versions |
| | `deletePrompts(ids)` | Delete prompts |
| **Attachments** | `getAttachmentClient()` | Get attachment client |
| **Utilities** | `authCheck()` | Verify credentials |
| | `flush()` | Send pending data |
| | `getConfig()` | Get configuration |
| | `getProjectUrl()` | Get project URL |

### Trace Methods

| Method | Description |
|--------|-------------|
| `span(name, type?, ...)` | Create child span |
| `update(output?, ...)` | Update trace data |
| `end()` | End the trace |
| `logFeedbackScore(name, value, ...)` | Log feedback score |
| `getId()` | Get trace ID |

### Span Methods

| Method | Description |
|--------|-------------|
| `span(name, type?, ...)` | Create child span |
| `update(output?, model?, usage?, ...)` | Update span data |
| `end()` | End the span |
| `logFeedbackScore(name, value, ...)` | Log feedback score |
| `getId()` | Get span ID |

---

## Development

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run with coverage (requires pcov/xdebug)
composer test:coverage

# Static analysis
composer analyse

# Code formatting
composer format
composer format:check
```

---

## License

MIT

## Trademarks

Opik and Comet ML are trademarks of Comet ML, Inc. This project is not affiliated with, endorsed by, or sponsored by Comet ML, Inc.
