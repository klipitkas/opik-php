# Changelog

All notable changes to the Opik PHP SDK will be documented in this file.

## [0.18.0] - 2025-12-18

### Added

- New heuristic evaluation metrics:
  - `Equals` metric for equality comparison with strict/loose mode support
  - `LevenshteinRatio` metric for text similarity based on edit distance
- Unit tests for new metrics (25 new tests)

### Fixed

- Fixed inline fully qualified class name in Evaluator.php (now uses imported FeedbackScore)

## [0.17.0] - 2025-12-18

### Added

- `evaluate()` function for running evaluations against datasets with metrics
  - `Evaluator` class for orchestrating evaluations
  - `EvaluationResult` class with average score calculation
  - `EvaluationTestResult` class for per-item results
- Convenience `$client->evaluate()` method on OpikClient
- Unit tests for evaluation framework (9 new tests)
- Evaluation documentation in README

### Changed

- Updated SDK comparison coverage to 75%

## [0.16.0] - 2025-12-18

### Added

- `CostCalculator` utility class for calculating LLM costs
  - `calculate()` method using per-token pricing
  - `calculateFromMillionPricing()` convenience method for per-million token pricing
- Unit tests for cost calculation (7 new tests)
- Cost calculation documentation in README

### Changed

- Updated SDK comparison coverage to 70%

## [0.15.0] - 2025-12-18

### Added

- Heuristic evaluation metrics framework:
  - `BaseMetric` abstract class for creating custom metrics
  - `ScoreResult` value object for metric results
  - `ExactMatch` metric for exact string comparison
  - `Contains` metric with case-sensitivity option
  - `RegexMatch` metric for pattern matching
  - `IsJson` metric for JSON validation
- Unit tests for all metrics (34 new tests)
- Metrics documentation in README with usage examples

### Changed

- Updated SDK comparison coverage to 65%

## [0.14.0] - 2025-12-18

### Added

- `Dataset::insertFromJson()` method for importing dataset items from JSON strings
  - Supports key mapping to rename fields during import
  - Supports ignoring specific keys
- `Dataset::toJson()` method for exporting dataset items to JSON strings
  - Supports key mapping to rename fields during export
- Unit tests for JSON import/export functionality

## [0.13.0] - 2025-12-18

### Changed

- Reduced batch size from 5MB to 1MB for better memory usage
- Added 25 item count limit per batch
- Added 10-second time-based flush interval
- Removed unused `Config` parameter from `BatchQueue` constructor

### Added

- Unit tests for batch count limit, size limit, and time-based flushing

## [0.12.0] - 2025-12-17

### Removed

- Unused TrackHandler, Track attribute, and TraceContext classes

## [0.11.0] - 2025-12-17

### Added

- SDK comparison table in README showing feature coverage vs Python and TypeScript SDKs

### Changed

- License changed from Apache-2.0 to MIT

## [0.10.0] - 2025-12-17

### Added

- Integration tests for Dataset operations (`DatasetIntegrationTest`)
- Integration tests for Prompt operations (`PromptIntegrationTest`)
- Unit tests for BatchQueue message deduplication
- `getProjectByName()` method for retrieving a project by name
- `deleteTraces()` method for batch deleting traces by IDs
- `deleteProjects()` method for batch deleting projects by IDs
- `deleteProject()` method for deleting a single project by ID
- CI workflow now runs integration tests against Opik Cloud

### Fixed

- BatchQueue now deduplicates trace and span messages by ID, keeping only the latest version
- Previously, multiple updates to the same trace/span could result in lost updates due to race conditions

## [0.9.0] - 2025-12-17

### Added

- GitHub Actions CI workflow for automated testing on PHP 8.1, 8.2, 8.3, and 8.4
- PCOV coverage driver support in CI

### Changed

- Renamed default branch from `master` to `main`

## [0.8.0] - 2025-12-16

### Added

- Chat prompts support with `ChatMessage` class and factory methods (`system`, `user`, `assistant`, `tool`)
- `ChatMessageRole` enum for message roles (OpenAI chat format)
- `TemplateStructure` enum for prompt types (text/chat)
- `PromptVersion::isChat()` and `PromptVersion::isText()` helper methods
- Unit tests for `ChatMessage` and `PromptVersion` (127 tests, 321 assertions)

### Changed

- `createPrompt()` now accepts `string|array<ChatMessage>` for both text and chat prompts
- `PromptVersion` handles `template_structure` from API responses
- Added `mustache` to `PromptType` enum (returned by API)

### Fixed

- Template variable replacement now works correctly with `{{variable}}` syntax

## [0.7.0] - 2025-12-16

### Added

- `authCheck()` method to verify API credentials
- Batch feedback scores for traces via `logTracesFeedbackScores()`
- Batch feedback scores for spans via `logSpansFeedbackScores()`
- Batch feedback scores for threads via `logThreadsFeedbackScores()`
- `FeedbackScore` class with factory methods (`forTrace()`, `forSpan()`, `forThread()`)
- Thread support with `closeThread()` and `closeThreads()` methods
- Attachments support via `AttachmentClient`:
  - `uploadAttachment()` - Upload files to traces or spans
  - `getAttachmentList()` - List attachments for an entity
  - `downloadAttachment()` - Download attachment content
- `deleteTraceFeedbackScore()` and `deleteSpanFeedbackScore()` methods
- Unit tests for new features (108 tests, 274 assertions)
- `test:coverage` composer script

### Changed

- Reorganized README with table of contents and better navigation
- Configuration section moved after Quick Start for better discoverability

## [0.6.1] - 2025-12-16

### Changed

- Clarified SDK is community-maintained in README

## [0.6.0] - 2025-12-16

### Added

- PHP CS Fixer configuration for consistent code formatting
- Pre-commit hook for automatic formatting and testing
- `composer format` and `composer lint` commands

### Changed

- Lowered PHP version requirement from 8.4 to 8.1 for broader compatibility

## [0.5.0] - 2025-12-16

### Added

- `getExperimentsByName()` method for retrieving all experiments with a given name
- `getDatasetExperiments()` method for retrieving all experiments for a dataset
- `searchPrompts()` method for searching prompts by name
- `Dataset::update()` method for bulk updating dataset items

## [0.4.0] - 2025-12-16

### Added
- `getPrompts()` method for listing prompts with pagination
- `getExperiments()` method for listing experiments with pagination (supports filtering by datasetId)
- Gzip request compression support (enabled by default)
- `OPIK_ENABLE_COMPRESSION` environment variable to control compression
- `enableCompression` config option

### Changed
- `Experiment.datasetName` is now nullable to handle API responses where dataset_name may be null

### Documentation
- Updated README with DatasetItem flexible schema documentation

## [0.3.0] - 2025-12-16

### Added
- Flexible schema support for DatasetItem with arbitrary fields
- `DatasetItem::getContent()`, `get()`, `getInput()`, `getExpectedOutput()`, `getMetadata()` accessor methods
- `data` parameter for DatasetItem constructor

## [0.2.0] - 2025-12-15

### Added
- Initial public release
- Tracing support (traces, spans, nested spans)
- Dataset management
- Experiment management
- Prompt management
- Feedback scores
- Thread support
- Batch queue for efficient API communication
