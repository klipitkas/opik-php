# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Architecture Overview

The Opik PHP SDK follows a **layered architecture** with clear separation of concerns:

### Core Layers

- **OpikClient** (`src/Opik/OpikClient.php`): Main entry point - factory for traces, spans, datasets, experiments, prompts
- **Tracer** (`src/Opik/Tracer/`): Trace and Span entities with support for nested spans, feedback scores, and LLM metadata
- **Message Processing** (`src/Opik/Message/`): Non-blocking batch queue with automatic flush on shutdown
- **API** (`src/Opik/Api/`): HTTP client with retry logic and authentication
- **Config** (`src/Opik/Config/`): Configuration from environment variables and constructor parameters

### Key Patterns

- **Non-blocking Operations**: Traces/spans queued via BatchQueue, flushed automatically on shutdown
- **Interface-based Design**: HttpClientInterface for testability
- **Immutable Entities**: Readonly properties where possible
- **WeakReference Context**: TraceContext uses WeakReference to prevent memory leaks
- **PHP 8.4 Features**: Readonly classes, enums, attributes, named arguments, constructor property promotion

### Directory Structure

```
src/Opik/
├── OpikClient.php              # Main client entry point
├── Api/                        # HTTP client and interfaces
├── Config/                     # Configuration management
├── Context/                    # Thread-safe trace context
├── Dataset/                    # Dataset and DatasetItem entities
├── Decorator/                  # Track attribute and handler
├── Exception/                  # Custom exceptions
├── Experiment/                 # Experiment and ExperimentItem entities
├── Message/                    # Batch queue and message types
├── Prompt/                     # Prompt and PromptVersion entities
├── Tracer/                     # Trace, Span, Usage, ErrorInfo entities
└── Utils/                      # IdGenerator, JsonEncoder
```

## Development Commands

### Environment Setup

```bash
# Install dependencies
composer install

# Install dev dependencies
composer install --dev
```

### Linting and Static Analysis

```bash
# Run PHPStan (level 5)
./vendor/bin/phpstan analyse

# Run PHP CS Fixer
./vendor/bin/php-cs-fixer fix --dry-run --diff
./vendor/bin/php-cs-fixer fix  # Apply fixes
```

### Testing

```bash
# Run all tests
./vendor/bin/phpunit

# Run with testdox output
./vendor/bin/phpunit --testdox

# Run specific test file
./vendor/bin/phpunit tests/Unit/OpikClientTest.php

# Run specific test method
./vendor/bin/phpunit --filter testShouldCreateTraceWithName
```

## Code Standards

### PHP Version

- Requires PHP 8.4+
- Uses modern PHP features: readonly classes, enums, attributes, named arguments

### Naming Conventions

- **Classes**: PascalCase (`OpikClient`, `BatchQueue`)
- **Methods**: camelCase (`createTrace`, `logFeedbackScore`)
- **Constants**: UPPER_SNAKE_CASE (`CLOUD_BASE_URL`, `MAX_BATCH_SIZE`)
- **Enum Cases**: UPPER_CASE (`SpanType::LLM`, `MessageType::CREATE_TRACE`)

### Import Strategy

- Use `HttpClientInterface` not `HttpClient` for type hints
- Import classes at top of file, not inline

### Architecture Principles

- **Single Responsibility**: Each class has one clear purpose
- **Dependency Injection**: Pass dependencies via constructor
- **Interface Segregation**: Use interfaces for external dependencies
- **Error Handling**: Non-blocking - errors don't interrupt user code

### Testing Philosophy

- Test public APIs, not internal implementation
- Mock HttpClientInterface for unit tests
- Use descriptive test names: `shouldCreateTraceWithValidInput`
- Group tests by feature in separate test classes

## Configuration Files

- **composer.json**: Dependencies and PSR-4 autoloading
- **phpunit.xml**: PHPUnit configuration
- **phpstan.neon**: PHPStan level 5 configuration
- **.php-cs-fixer.php**: PSR-12 code style configuration

## Key Classes

### OpikClient

Main entry point. Creates traces, spans, datasets, experiments, prompts.

```php
$client = new OpikClient(
    apiKey: 'key',
    workspace: 'workspace',
    projectName: 'project',
);

$trace = $client->trace('operation-name');
$span = $trace->span('llm-call', type: SpanType::LLM);
```

### BatchQueue

Non-blocking message queue. Messages batched and sent automatically.

```php
// Messages queued, not sent immediately
$trace->end();

// Force flush all pending messages
$client->flush();

// Auto-flush on shutdown via register_shutdown_function
```

### TraceContext

Thread-safe context for tracking current trace/span. Uses WeakReference.

```php
TraceContext::setCurrentTrace($trace);
TraceContext::pushSpan($span);
$current = TraceContext::getCurrentSpan();
TraceContext::popSpan();
```

## API Endpoints

The SDK communicates with these Opik API endpoints:

- `POST /v1/private/traces` - Batch create/update traces
- `POST /v1/private/spans` - Batch create/update spans
- `GET/POST /v1/private/datasets` - Dataset CRUD
- `GET/POST /v1/private/experiments` - Experiment CRUD
- `GET/POST /v1/private/prompts` - Prompt CRUD
- `PUT /v1/private/traces/feedback-scores` - Batch feedback scores
- `PUT /v1/private/spans/feedback-scores` - Batch feedback scores

## Important Notes

- All enum cases use UPPER_CASE (`SpanType::LLM`, not `SpanType::Llm`)
- Entity classes use `HttpClientInterface`, not concrete `HttpClient`
- TraceContext uses WeakReference to prevent memory leaks
- BatchQueue registers global shutdown handler for auto-flush
- Input validation uses `empty(trim($value))` pattern
