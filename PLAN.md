# PHP SDK Implementation Plan

## Overview

This document outlines the implementation plan for the Opik PHP SDK, following the patterns established by the Python and TypeScript SDKs, adhering to PSR-4 autoloading, PHP 8.4 best practices, and the project's clean code guidelines.

## Directory Structure

```
sdks/php/
├── src/
│   └── Opik/
│       ├── OpikClient.php              # Main client entry point
│       ├── Config/
│       │   ├── Config.php              # Configuration management
│       │   └── Environment.php         # Environment variable handling
│       ├── Api/
│       │   ├── HttpClient.php          # HTTP client wrapper
│       │   ├── Authentication.php      # Auth header handling
│       │   └── RetryHandler.php        # Exponential backoff retry logic
│       ├── Message/
│       │   ├── BatchQueue.php          # Non-blocking batch queue
│       │   ├── MessageProcessor.php    # Background message processing
│       │   └── Messages.php            # Message type definitions
│       ├── Tracer/
│       │   ├── Trace.php               # Trace entity
│       │   └── Span.php                # Span entity (types: llm, tool, general, guardrail)
│       ├── Dataset/
│       │   ├── Dataset.php             # Dataset entity
│       │   └── DatasetItem.php         # Dataset item entity
│       ├── Experiment/
│       │   ├── Experiment.php          # Experiment entity
│       │   └── ExperimentItem.php      # Experiment item entity
│       ├── Prompt/
│       │   ├── Prompt.php              # Prompt entity
│       │   └── PromptVersion.php       # Prompt version entity
│       ├── FeedbackScore/
│       │   └── FeedbackScore.php       # Numerical/categorical feedback scores
│       ├── Decorator/
│       │   └── Track.php               # PHP 8.4 attribute for method tracking
│       ├── Context/
│       │   └── TraceContext.php        # Thread-safe context management
│       ├── Utils/
│       │   ├── IdGenerator.php         # UUID generation
│       │   ├── JsonEncoder.php         # JSON encoding utilities
│       │   └── UrlHelper.php           # URL construction helpers
│       └── Exception/
│           ├── OpikException.php       # Base exception
│           ├── ConfigurationException.php
│           └── ApiException.php
├── tests/
│   ├── Unit/
│   │   ├── Config/
│   │   ├── Api/
│   │   ├── Message/
│   │   ├── Tracer/
│   │   └── ...
│   └── Integration/
│       └── ...
├── examples/
│   ├── basic-tracing.php
│   ├── decorator-usage.php
│   └── dataset-experiment.php
├── composer.json
├── phpunit.xml
├── phpstan.neon
├── .php-cs-fixer.php
└── README.md
```

## Implementation Phases

### Phase 1: Project Setup

**Tasks:**
1. Create `composer.json` with PSR-4 autoloading
2. Configure dependencies:
   - `guzzlehttp/guzzle` - HTTP client
   - `ramsey/uuid` - UUID generation
   - `psr/log` - Logging interface
3. Set up development dependencies:
   - `phpunit/phpunit` - Testing
   - `phpstan/phpstan` - Static analysis (level 9)
   - `friendsofphp/php-cs-fixer` - Code style (PSR-12)
4. Create `phpunit.xml` configuration
5. Create `phpstan.neon` configuration
6. Create `.php-cs-fixer.php` configuration

**PHP 8.4 Features to Use:**
- Property hooks for validation
- Asymmetric visibility
- Constructor property promotion
- Named arguments
- Attributes (for Track decorator)
- Readonly classes where appropriate
- Union types and intersection types
- `never` return type
- First-class callables

### Phase 2: Core Infrastructure

#### 2.1 Configuration (`src/Opik/Config/`)

**Environment Variables (priority order):**
1. Constructor parameters
2. Environment variables:
   - `OPIK_API_KEY`
   - `OPIK_WORKSPACE`
   - `OPIK_PROJECT_NAME`
   - `OPIK_URL_OVERRIDE`
3. Configuration file (`~/.opik.config`)
4. Default values

**API Endpoints:**
- Cloud: `https://www.comet.com/opik/api/`
- Local: `http://localhost:5173/api/`

#### 2.2 HTTP Client (`src/Opik/Api/`)

**Features:**
- Guzzle HTTP wrapper
- Authentication headers (`authorization`, `Comet-Workspace`)
- Retry logic with exponential backoff
- Request/response logging
- Timeout configuration

#### 2.3 Message Processing (`src/Opik/Message/`)

**Non-blocking Architecture:**
- Batch queue with size-based flushing (max 5MB)
- Time-based flushing (configurable interval)
- `flush()` method for immediate sending
- Automatic flush on shutdown (`register_shutdown_function`)
- Error handling that doesn't interrupt user code

### Phase 3: Core Entities

#### 3.1 Trace (`src/Opik/Tracer/Trace.php`)

**Properties:**
- `id`: string (UUID)
- `name`: string
- `projectName`: string
- `input`: mixed
- `output`: mixed
- `metadata`: array
- `tags`: array
- `startTime`: DateTimeImmutable
- `endTime`: ?DateTimeImmutable
- `errorInfo`: ?array

**Methods:**
- `span()`: Create child span
- `update()`: Update trace properties
- `end()`: End trace
- `logFeedbackScore()`: Add feedback score

#### 3.2 Span (`src/Opik/Tracer/Span.php`)

**Properties:**
- `id`: string (UUID)
- `traceId`: string
- `parentSpanId`: ?string
- `name`: string
- `type`: SpanType enum (`llm`, `tool`, `general`, `guardrail`)
- `input`: mixed
- `output`: mixed
- `metadata`: array
- `startTime`: DateTimeImmutable
- `endTime`: ?DateTimeImmutable
- `usage`: ?array (tokens, cost)
- `model`: ?string
- `provider`: ?string
- `errorInfo`: ?array

**Methods:**
- `span()`: Create child span
- `update()`: Update span properties
- `end()`: End span
- `logFeedbackScore()`: Add feedback score

#### 3.3 Dataset (`src/Opik/Dataset/`)

**Dataset Properties:**
- `id`: string
- `name`: string
- `description`: ?string
- `items`: array

**Methods:**
- `insert()`: Add items
- `delete()`: Remove items
- `getItems()`: Retrieve items
- `toDataFrame()`: Export as array

#### 3.4 Experiment (`src/Opik/Experiment/`)

**Properties:**
- `id`: string
- `name`: string
- `datasetName`: string
- `status`: ExperimentStatus enum

**Methods:**
- `logItems()`: Log experiment items
- `getItems()`: Retrieve items

#### 3.5 Prompt (`src/Opik/Prompt/`)

**Properties:**
- `id`: string
- `name`: string
- `template`: string
- `versions`: array

**Methods:**
- `format()`: Apply variables to template
- `getVersion()`: Get specific version

#### 3.6 FeedbackScore (`src/Opik/FeedbackScore/`)

**Types:**
- Numerical (0-1 float)
- Categorical (string labels)

### Phase 4: Main Client

#### OpikClient (`src/Opik/OpikClient.php`)

**Factory Methods:**
```php
public function trace(
    string $name,
    ?string $projectName = null,
    mixed $input = null,
    ?array $metadata = null,
    ?array $tags = null,
): Trace;

public function span(
    string $name,
    string $traceId,
    ?string $parentSpanId = null,
    SpanType $type = SpanType::General,
    mixed $input = null,
): Span;

public function getDataset(string $name): Dataset;

public function createDataset(
    string $name,
    ?string $description = null,
): Dataset;

public function getExperiment(string $name): Experiment;

public function getPrompt(string $name, ?string $version = null): Prompt;

public function flush(): void;
```

### Phase 5: Developer Experience

#### 5.1 Track Attribute (`src/Opik/Decorator/Track.php`)

PHP 8.4 attribute for automatic method tracing:

```php
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Track
{
    public function __construct(
        public ?string $name = null,
        public ?string $projectName = null,
        public SpanType $type = SpanType::General,
    ) {}
}
```

**Usage:**
```php
class MyService
{
    #[Track(name: 'generate-response', type: SpanType::Llm)]
    public function generateResponse(string $prompt): string
    {
        // Method body
    }
}
```

**Implementation:**
- Use reflection to detect tracked methods
- Wrap method execution with span creation
- Capture input/output automatically
- Handle exceptions gracefully

#### 5.2 Context Management (`src/Opik/Context/`)

**Features:**
- Thread-safe context storage
- Nested span support
- Current trace/span retrieval
- Fiber-aware context (PHP 8.1+)

### Phase 6: Quality Assurance

#### 6.1 Testing Strategy

**Unit Tests:**
- Mock HTTP client
- Test each entity in isolation
- Test configuration priority
- Test batch queue logic

**Integration Tests:**
- Test against local Opik instance
- Full trace/span lifecycle
- Dataset operations
- Experiment workflows

**Test Naming Convention:**
```php
public function testShouldCreateTraceWithValidInput(): void
public function testShouldThrowExceptionForInvalidApiKey(): void
```

#### 6.2 Static Analysis

**PHPStan Level 9:**
- Strict type checking
- No mixed types without explicit handling
- Complete PHPDoc annotations

#### 6.3 Code Style

**PSR-12 with enhancements:**
- `final` classes by default
- `readonly` properties where immutable
- No `else` when possible (early returns)
- Single responsibility methods

### Phase 7: Documentation

1. README.md with quick start
2. Examples directory with common use cases
3. PHPDoc for all public APIs
4. CLAUDE.md for AI assistance

## Constants and Configuration

```php
final readonly class ApiConstants
{
    public const string CLOUD_BASE_URL = 'https://www.comet.com/opik/api/';
    public const string LOCAL_BASE_URL = 'http://localhost:5173/api/';
    public const int DEFAULT_TIMEOUT_MS = 30000;
    public const int MAX_BATCH_SIZE_BYTES = 5 * 1024 * 1024; // 5MB
    public const int FLUSH_INTERVAL_MS = 1000;
    public const int MAX_RETRIES = 3;
}
```

## Error Handling

**Principles:**
- Non-blocking: errors don't interrupt user code
- Comprehensive logging
- Retry transient failures
- Clear exception hierarchy

## API Endpoints

Core endpoints to implement:
- `POST /v1/private/traces` (batch create/update)
- `POST /v1/private/spans` (batch create/update)
- `GET/POST /v1/private/datasets`
- `GET/POST /v1/private/experiments`
- `GET/POST /v1/private/prompts`
- `POST /v1/private/feedback-scores`

## Dependencies

```json
{
    "require": {
        "php": "^8.4",
        "guzzlehttp/guzzle": "^7.9",
        "ramsey/uuid": "^4.7",
        "psr/log": "^3.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0",
        "phpstan/phpstan": "^2.0",
        "friendsofphp/php-cs-fixer": "^3.64"
    }
}
```

## Timeline Estimate

| Phase | Description | Effort |
|-------|-------------|--------|
| 1 | Project Setup | 1 day |
| 2 | Core Infrastructure | 3 days |
| 3 | Core Entities | 4 days |
| 4 | Main Client | 2 days |
| 5 | Developer Experience | 3 days |
| 6 | Quality Assurance | 3 days |
| 7 | Documentation | 1 day |

**Total: ~17 days**

## Clean Code Principles Applied

Following `.cursor/rules/clean-code.mdc`:

1. **Constants Over Magic Numbers** - All config values as named constants
2. **Meaningful Names** - Descriptive class/method names
3. **Smart Comments** - Only explain "why", not "what"
4. **Single Responsibility** - One purpose per class/method
5. **DRY** - Shared utilities, base classes
6. **Clean Structure** - Logical directory hierarchy
7. **Encapsulation** - Private implementation, public interfaces
8. **Testing** - Comprehensive test coverage

---

## Missing Features (Compared to Python/TypeScript SDKs)

### Implemented Features
- [x] Basic tracing (trace, span, nested spans)
- [x] Feedback scores on traces and spans
- [x] Datasets (CRUD, insert, get items)
- [x] Experiments (create, log items)
- [x] Prompts (get, format, versions)
- [x] Batch queue with auto-flush
- [x] `thread_id` support on traces
- [x] `total_cost` support on spans
- [x] `searchTraces()` / `searchSpans()`
- [x] `getTraceContent()` / `getSpanContent()`
- [x] `deleteDataset()` / `deleteExperiment()`
- [x] `createPrompt()`
- [x] Batch feedback scores (`logTracesFeedbackScores`, `logSpansFeedbackScores`)

### Medium Priority (Management Features)
- [x] `updateExperiment()` - Update experiment metadata
- [x] `getProject()` / `getProjectUrl()` - Get project information and URLs
- [x] `getDatasets()` - List all datasets with pagination
- [x] `deletePrompts()` - Batch delete prompts
- [x] `getPromptHistory()` - Get all versions of a prompt
- [x] `deleteFeedbackScore()` - Delete specific feedback scores (`deleteTraceFeedbackScore`, `deleteSpanFeedbackScore`)
- [x] `getExperimentById()` - Get experiment by ID
- [ ] Attachments support on traces/spans - File uploads

### Lower Priority (Advanced Features)
- [ ] Evaluation framework (`evaluate()`) - Evaluate tasks against datasets
- [ ] Built-in metrics (Hallucination, Factuality, BLEU, ROUGE, etc.)
- [ ] LLM integrations (OpenAI, Anthropic, etc.)
- [ ] Framework integrations (LangChain, LlamaIndex, etc.)
- [ ] OQL query builder helper class
- [ ] Context managers for spans/traces
- [ ] Simulation framework
- [ ] Advanced decorator features (capture_input, capture_output, ignore_arguments)

### Testing Gaps
- [ ] Integration tests (tests/Integration/ is empty)
- [x] Unit tests for new OpikClient methods
- [ ] Error scenario tests (API failures, network issues)
- [x] TraceContext tests

### Documentation Gaps
- [ ] Update README.md with new methods
- [ ] Add examples for new features (search, batch feedback, etc.)
- [ ] CLAUDE.md for AI assistance
