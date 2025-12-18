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
- **PHP 8.1+ Features**: Enums, attributes, named arguments, constructor property promotion, readonly properties

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

### Linting, Formatting and Static Analysis

```bash
# Format code (auto-fix)
composer format

# Check formatting without fixing
composer format:check

# Run PHPStan static analysis
composer analyse

# Run both (lint = analyse + format:check)
composer lint
```

### Git Hooks Setup

```bash
# Enable pre-commit hook (runs format + tests before commit)
git config core.hooksPath .githooks
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

- Requires PHP 8.1+
- Uses modern PHP features: readonly properties, enums, attributes, named arguments

### Naming Conventions

- **Classes**: PascalCase (`OpikClient`, `BatchQueue`)
- **Methods**: camelCase (`createTrace`, `logFeedbackScore`)
- **Constants**: UPPER_SNAKE_CASE (`CLOUD_BASE_URL`, `MAX_BATCH_SIZE`)
- **Enum Cases**: UPPER_CASE (`SpanType::LLM`, `MessageType::CREATE_TRACE`)

### Import Strategy

- Use `HttpClientInterface` not `HttpClient` for type hints
- Always import classes with `use` statements at the top of the file
- Never use fully qualified class names inline (e.g., use `FeedbackScore::forTrace()` not `\Opik\Feedback\FeedbackScore::forTrace()`)

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

### Integration Tests

- **Always verify values, not just keys** - Assert that sent values match retrieved values
- **Assert exact counts** - Use `assertCount()` when creating known quantities
- **Clean up in tearDown()** - Delete test data using `deleteTraces()`
- **Wait for backend** - Use `usleep(500000)` after flush for processing

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

## Before Committing

Always run `composer format` before committing to ensure consistent code style. Or enable the pre-commit hook:

```bash
git config core.hooksPath .githooks
```

## Release instructions

1. Run `composer format` - Format all code
2. Run `composer test` - Ensure all tests pass
3. Update README.md - Document any new public methods/features added, update SDK coverare and summary if needed
4. Update CHANGELOG.md - Add entry for the new version with changes
5. Update composer.json version - Follow semver:
   - Patch (0.0.X) for bug fixes
   - Minor (0.X.0) for new features (backwards compatible)
   - Major (X.0.0) for breaking changes
6. Create git tag - Tag the release with the version (e.g., v0.5.0)
7. Commit and push - Push all changes and tag to master
