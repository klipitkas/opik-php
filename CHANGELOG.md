# Changelog

All notable changes to the Opik PHP SDK will be documented in this file.

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
