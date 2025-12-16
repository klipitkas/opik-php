<?php

declare(strict_types=1);

namespace Opik;

use DateTimeImmutable;
use InvalidArgumentException;
use Opik\Api\HttpClient;
use Opik\Api\HttpClientInterface;
use Opik\Config\Config;
use Opik\Dataset\Dataset;
use Opik\Exception\ConfigurationException;
use Opik\Exception\OpikException;
use Opik\Experiment\Experiment;
use Opik\Message\BatchQueue;
use Opik\Prompt\Prompt;
use Opik\Tracer\Span;
use Opik\Tracer\SpanType;
use Opik\Tracer\Trace;
use Opik\Utils\IdGenerator;
use Psr\Log\LoggerInterface;

/**
 * Main client for interacting with the Opik API.
 *
 * The OpikClient provides methods for creating traces, spans, datasets,
 * experiments, and prompts. It handles batching of messages for efficient
 * API communication and automatic flushing on shutdown.
 *
 * @example
 * ```php
 * $client = new OpikClient(
 *     apiKey: 'your-api-key',
 *     workspace: 'your-workspace',
 *     projectName: 'my-project',
 * );
 *
 * $trace = $client->trace('my-operation');
 * $span = $trace->span('llm-call', type: SpanType::LLM);
 * $span->update(output: ['response' => 'Hello!']);
 * $span->end();
 * $trace->end();
 * ```
 */
final class OpikClient
{
    private readonly Config $config;

    private readonly HttpClientInterface $httpClient;

    private readonly BatchQueue $batchQueue;

    /**
     * Create a new Opik client.
     *
     * @param string|null $apiKey API key for authentication (or set OPIK_API_KEY env var)
     * @param string|null $workspace Workspace name (or set OPIK_WORKSPACE env var)
     * @param string|null $projectName Default project name for traces
     * @param string|null $baseUrl API base URL (defaults to cloud or OPIK_URL_OVERRIDE env var)
     * @param bool $debug Enable debug mode
     * @param LoggerInterface|null $logger PSR-3 logger for debug output
     *
     * @throws ConfigurationException If required configuration is missing
     */
    public function __construct(
        ?string $apiKey = null,
        ?string $workspace = null,
        ?string $projectName = null,
        ?string $baseUrl = null,
        bool $debug = false,
        ?LoggerInterface $logger = null,
    ) {
        $this->config = new Config(
            apiKey: $apiKey,
            workspace: $workspace,
            projectName: $projectName,
            baseUrl: $baseUrl,
            debug: $debug,
        );

        $this->validateConfig();

        $this->httpClient = new HttpClient($this->config, $logger);
        $this->batchQueue = new BatchQueue($this->httpClient, $this->config, $logger);
    }

    /**
     * Create a new trace.
     *
     * @param string $name The name of the trace
     * @param string|null $projectName Project name (uses default if not provided)
     * @param string|null $id Custom trace ID (UUID v7 generated if not provided)
     * @param mixed $input Input data for the trace
     * @param array<string, mixed>|null $metadata Metadata key-value pairs
     * @param array<int, string>|null $tags List of tags
     * @param DateTimeImmutable|null $startTime Start time (current time if not provided)
     * @param string|null $threadId Thread ID for grouping related traces
     *
     * @throws InvalidArgumentException If name is empty
     *
     * @return Trace The created trace
     */
    public function trace(
        string $name,
        ?string $projectName = null,
        ?string $id = null,
        mixed $input = null,
        ?array $metadata = null,
        ?array $tags = null,
        ?DateTimeImmutable $startTime = null,
        ?string $threadId = null,
    ): Trace {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Trace name cannot be empty');
        }

        return new Trace(
            batchQueue: $this->batchQueue,
            name: $name,
            projectName: $projectName ?? $this->config->projectName ?? 'Default Project',
            id: $id,
            startTime: $startTime,
            input: $input,
            metadata: $metadata,
            tags: $tags,
            threadId: $threadId,
        );
    }

    /**
     * Create a new span directly (typically use Trace::span() instead).
     *
     * @param string $name The name of the span
     * @param string $traceId The parent trace ID
     * @param string|null $projectName Project name (uses default if not provided)
     * @param string|null $parentSpanId Parent span ID for nesting
     * @param SpanType $type The type of span (General, Llm, Tool)
     * @param string|null $id Custom span ID (UUID v7 generated if not provided)
     * @param mixed $input Input data for the span
     * @param array<string, mixed>|null $metadata Metadata key-value pairs
     * @param array<int, string>|null $tags List of tags
     * @param DateTimeImmutable|null $startTime Start time (current time if not provided)
     *
     * @throws InvalidArgumentException If name or traceId is empty
     *
     * @return Span The created span
     */
    public function span(
        string $name,
        string $traceId,
        ?string $projectName = null,
        ?string $parentSpanId = null,
        SpanType $type = SpanType::GENERAL,
        ?string $id = null,
        mixed $input = null,
        ?array $metadata = null,
        ?array $tags = null,
        ?DateTimeImmutable $startTime = null,
    ): Span {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Span name cannot be empty');
        }

        if (empty(trim($traceId))) {
            throw new InvalidArgumentException('Trace ID cannot be empty');
        }

        return new Span(
            batchQueue: $this->batchQueue,
            traceId: $traceId,
            name: $name,
            projectName: $projectName ?? $this->config->projectName ?? 'Default Project',
            parentSpanId: $parentSpanId,
            type: $type,
            id: $id,
            startTime: $startTime,
            input: $input,
            metadata: $metadata,
            tags: $tags,
        );
    }

    /**
     * Get an existing dataset by name.
     *
     * @param string $name The dataset name
     *
     * @throws InvalidArgumentException If name is empty
     * @throws OpikException If the dataset is not found
     *
     * @return Dataset The dataset
     */
    public function getDataset(string $name): Dataset
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Dataset name cannot be empty');
        }

        $response = $this->httpClient->get('v1/private/datasets', [
            'name' => $name,
        ]);

        $datasets = $response['content'] ?? [];

        foreach ($datasets as $dataset) {
            if ($dataset['name'] === $name) {
                return new Dataset(
                    httpClient: $this->httpClient,
                    id: $dataset['id'],
                    name: $dataset['name'],
                    description: $dataset['description'] ?? null,
                );
            }
        }

        throw new OpikException("Dataset not found: {$name}");
    }

    /**
     * Create a new dataset.
     *
     * @param string $name The dataset name
     * @param string|null $description Optional description
     *
     * @throws InvalidArgumentException If name is empty
     *
     * @return Dataset The created dataset
     */
    public function createDataset(string $name, ?string $description = null): Dataset
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Dataset name cannot be empty');
        }

        $id = IdGenerator::uuid();

        $this->httpClient->post('v1/private/datasets', [
            'id' => $id,
            'name' => $name,
            'description' => $description,
        ]);

        return new Dataset(
            httpClient: $this->httpClient,
            id: $id,
            name: $name,
            description: $description,
        );
    }

    /**
     * Get an existing dataset or create a new one if it doesn't exist.
     *
     * @param string $name The dataset name
     * @param string|null $description Optional description (used only when creating)
     *
     * @throws InvalidArgumentException If name is empty
     *
     * @return Dataset The dataset
     */
    public function getOrCreateDataset(string $name, ?string $description = null): Dataset
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Dataset name cannot be empty');
        }

        try {
            return $this->getDataset($name);
        } catch (OpikException) {
            return $this->createDataset($name, $description);
        }
    }

    /**
     * Get an existing experiment by name.
     *
     * @param string $name The experiment name
     *
     * @throws InvalidArgumentException If name is empty
     * @throws OpikException If the experiment is not found
     *
     * @return Experiment The experiment
     */
    public function getExperiment(string $name): Experiment
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Experiment name cannot be empty');
        }

        $response = $this->httpClient->get('v1/private/experiments', [
            'name' => $name,
        ]);

        $experiments = $response['content'] ?? [];

        foreach ($experiments as $experiment) {
            if ($experiment['name'] === $name) {
                return new Experiment(
                    httpClient: $this->httpClient,
                    id: $experiment['id'],
                    name: $experiment['name'],
                    datasetName: $experiment['dataset_name'],
                    datasetId: $experiment['dataset_id'] ?? null,
                );
            }
        }

        throw new OpikException("Experiment not found: {$name}");
    }

    /**
     * Get all experiments with a given name.
     *
     * Note: Multiple experiments can have the same name.
     *
     * @param string $name The experiment name
     *
     * @throws InvalidArgumentException If name is empty
     *
     * @return array<int, Experiment> List of experiments with the given name
     */
    public function getExperimentsByName(string $name): array
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Experiment name cannot be empty');
        }

        $response = $this->httpClient->get('v1/private/experiments', [
            'name' => $name,
        ]);

        return array_map(
            fn (array $experiment) => new Experiment(
                httpClient: $this->httpClient,
                id: $experiment['id'],
                name: $experiment['name'],
                datasetName: $experiment['dataset_name'] ?? null,
                datasetId: $experiment['dataset_id'] ?? null,
            ),
            $response['content'] ?? [],
        );
    }

    /**
     * Get all experiments for a dataset.
     *
     * @param string $datasetName The dataset name
     * @param int $maxResults Maximum number of experiments to return
     *
     * @throws InvalidArgumentException If datasetName is empty
     * @throws OpikException If the dataset is not found
     *
     * @return array<int, Experiment> List of experiments for the dataset
     */
    public function getDatasetExperiments(string $datasetName, int $maxResults = 100): array
    {
        if (empty(trim($datasetName))) {
            throw new InvalidArgumentException('Dataset name cannot be empty');
        }

        $dataset = $this->getDataset($datasetName);

        return $this->getExperiments($dataset->id, 1, $maxResults);
    }

    /**
     * Get all experiments with pagination.
     *
     * @param string|null $datasetId Filter by dataset ID
     * @param int $page Page number (1-based)
     * @param int $size Page size
     *
     * @return array<int, Experiment> List of experiments
     */
    public function getExperiments(?string $datasetId = null, int $page = 1, int $size = 100): array
    {
        $query = ['page' => $page, 'size' => $size];

        if ($datasetId !== null) {
            $query['dataset_id'] = $datasetId;
        }

        $response = $this->httpClient->get('v1/private/experiments', $query);

        return array_map(
            fn (array $experiment) => new Experiment(
                httpClient: $this->httpClient,
                id: $experiment['id'],
                name: $experiment['name'],
                datasetName: $experiment['dataset_name'] ?? null,
                datasetId: $experiment['dataset_id'] ?? null,
            ),
            $response['content'] ?? [],
        );
    }

    /**
     * Create a new experiment.
     *
     * @param string $name The experiment name
     * @param string $datasetName The name of the dataset to associate
     * @param string|null $datasetId Optional dataset ID
     *
     * @throws InvalidArgumentException If name or datasetName is empty
     *
     * @return Experiment The created experiment
     */
    public function createExperiment(string $name, string $datasetName, ?string $datasetId = null): Experiment
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Experiment name cannot be empty');
        }

        if (empty(trim($datasetName))) {
            throw new InvalidArgumentException('Dataset name is required to create an experiment');
        }

        $id = IdGenerator::uuid();

        $data = [
            'id' => $id,
            'name' => $name,
            'dataset_name' => $datasetName,
        ];

        if ($datasetId !== null) {
            $data['dataset_id'] = $datasetId;
        }

        $this->httpClient->post('v1/private/experiments', $data);

        return new Experiment(
            httpClient: $this->httpClient,
            id: $id,
            name: $name,
            datasetName: $datasetName,
            datasetId: $datasetId,
        );
    }

    /**
     * Get an existing prompt by name.
     *
     * @param string $name The prompt name
     *
     * @throws InvalidArgumentException If name is empty
     * @throws OpikException If the prompt is not found
     *
     * @return Prompt The prompt
     */
    public function getPrompt(string $name): Prompt
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Prompt name cannot be empty');
        }

        $response = $this->httpClient->get('v1/private/prompts', [
            'name' => $name,
        ]);

        $prompts = $response['content'] ?? [];

        foreach ($prompts as $prompt) {
            if ($prompt['name'] === $name) {
                return new Prompt(
                    httpClient: $this->httpClient,
                    id: $prompt['id'],
                    name: $prompt['name'],
                );
            }
        }

        throw new OpikException("Prompt not found: {$name}");
    }

    /**
     * Create a new prompt.
     *
     * @param string $name The prompt name
     * @param string $template The prompt template
     * @param string|null $description Optional description
     * @param array<string, mixed>|null $metadata Optional metadata
     *
     * @throws InvalidArgumentException If name or template is empty
     *
     * @return Prompt The created prompt
     */
    public function createPrompt(
        string $name,
        string $template,
        ?string $description = null,
        ?array $metadata = null,
    ): Prompt {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Prompt name cannot be empty');
        }

        if (empty(trim($template))) {
            throw new InvalidArgumentException('Prompt template cannot be empty');
        }

        $id = IdGenerator::uuid();
        $versionId = IdGenerator::uuid();

        $this->httpClient->post('v1/private/prompts', [
            'id' => $id,
            'name' => $name,
            'description' => $description,
        ]);

        $this->httpClient->post('v1/private/prompts/versions', [
            'id' => $versionId,
            'prompt_id' => $id,
            'name' => $name,
            'template' => $template,
            'metadata' => $metadata,
        ]);

        return new Prompt(
            httpClient: $this->httpClient,
            id: $id,
            name: $name,
        );
    }

    /**
     * Delete a dataset by name.
     *
     * @param string $name The dataset name
     *
     * @throws InvalidArgumentException If name is empty
     * @throws OpikException If the dataset is not found
     */
    public function deleteDataset(string $name): void
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Dataset name cannot be empty');
        }

        $dataset = $this->getDataset($name);
        $this->httpClient->delete("v1/private/datasets/{$dataset->id}");
    }

    /**
     * Delete an experiment by name.
     *
     * @param string $name The experiment name
     *
     * @throws InvalidArgumentException If name is empty
     * @throws OpikException If the experiment is not found
     */
    public function deleteExperiment(string $name): void
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Experiment name cannot be empty');
        }

        $experiment = $this->getExperiment($name);
        $this->httpClient->post('v1/private/experiments/delete', ['ids' => [$experiment->id]]);
    }

    /**
     * Delete experiments by IDs.
     *
     * @param array<int, string> $ids List of experiment IDs to delete
     *
     * @throws InvalidArgumentException If ids array is empty
     */
    public function deleteExperiments(array $ids): void
    {
        if (empty($ids)) {
            throw new InvalidArgumentException('Experiment IDs array cannot be empty');
        }

        $this->httpClient->post('v1/private/experiments/delete', ['ids' => $ids]);
    }

    /**
     * Get trace content by ID.
     *
     * @param string $traceId The trace ID
     *
     * @throws InvalidArgumentException If traceId is empty
     *
     * @return array<string, mixed> The trace data
     */
    public function getTraceContent(string $traceId): array
    {
        if (empty(trim($traceId))) {
            throw new InvalidArgumentException('Trace ID cannot be empty');
        }

        return $this->httpClient->get("v1/private/traces/{$traceId}");
    }

    /**
     * Get span content by ID.
     *
     * @param string $spanId The span ID
     *
     * @throws InvalidArgumentException If spanId is empty
     *
     * @return array<string, mixed> The span data
     */
    public function getSpanContent(string $spanId): array
    {
        if (empty(trim($spanId))) {
            throw new InvalidArgumentException('Span ID cannot be empty');
        }

        return $this->httpClient->get("v1/private/spans/{$spanId}");
    }

    /**
     * Search for traces.
     *
     * @param string|null $projectName Filter by project name
     * @param string|null $filter OQL filter expression
     * @param int $page Page number (1-based)
     * @param int $size Page size
     *
     * @return array<string, mixed> Search results with 'content' and pagination info
     */
    public function searchTraces(
        ?string $projectName = null,
        ?string $filter = null,
        int $page = 1,
        int $size = 100,
    ): array {
        $query = [
            'page' => $page,
            'size' => $size,
        ];

        if ($projectName !== null) {
            $query['project_name'] = $projectName;
        } elseif ($this->config->projectName !== null) {
            $query['project_name'] = $this->config->projectName;
        }

        if ($filter !== null) {
            $query['filter'] = $filter;
        }

        return $this->httpClient->get('v1/private/traces', $query);
    }

    /**
     * Search for spans.
     *
     * @param string|null $traceId Filter by trace ID
     * @param string|null $projectName Filter by project name
     * @param string|null $filter OQL filter expression
     * @param int $page Page number (1-based)
     * @param int $size Page size
     *
     * @return array<string, mixed> Search results with 'content' and pagination info
     */
    public function searchSpans(
        ?string $traceId = null,
        ?string $projectName = null,
        ?string $filter = null,
        int $page = 1,
        int $size = 100,
    ): array {
        $query = [
            'page' => $page,
            'size' => $size,
        ];

        if ($traceId !== null) {
            $query['trace_id'] = $traceId;
        }

        if ($projectName !== null) {
            $query['project_name'] = $projectName;
        } elseif ($this->config->projectName !== null) {
            $query['project_name'] = $this->config->projectName;
        }

        if ($filter !== null) {
            $query['filter'] = $filter;
        }

        return $this->httpClient->get('v1/private/spans', $query);
    }

    /**
     * Log feedback scores for multiple traces in batch.
     *
     * @param array<int, array{trace_id: string, name: string, value: float|string, reason?: string, category_name?: string}> $scores
     */
    public function logTracesFeedbackScores(array $scores): void
    {
        $projectName = $this->config->projectName ?? 'Default Project';

        $formattedScores = array_map(function (array $score) use ($projectName): array {
            $data = [
                'id' => IdGenerator::uuid(),
                'trace_id' => $score['trace_id'],
                'name' => $score['name'],
                'source' => 'sdk',
                'project_name' => $projectName,
            ];

            if (\is_float($score['value'])) {
                $data['value'] = $score['value'];
            } else {
                $data['category_name'] = $score['category_name'] ?? $score['value'];
            }

            if (isset($score['reason'])) {
                $data['reason'] = $score['reason'];
            }

            return $data;
        }, $scores);

        $this->httpClient->put('v1/private/traces/feedback-scores', ['scores' => $formattedScores]);
    }

    /**
     * Log feedback scores for multiple spans in batch.
     *
     * @param array<int, array{span_id: string, name: string, value: float|string, reason?: string, category_name?: string}> $scores
     */
    public function logSpansFeedbackScores(array $scores): void
    {
        $projectName = $this->config->projectName ?? 'Default Project';

        $formattedScores = array_map(function (array $score) use ($projectName): array {
            $data = [
                'id' => IdGenerator::uuid(),
                'span_id' => $score['span_id'],
                'name' => $score['name'],
                'source' => 'sdk',
                'project_name' => $projectName,
            ];

            if (\is_float($score['value'])) {
                $data['value'] = $score['value'];
            } else {
                $data['category_name'] = $score['category_name'] ?? $score['value'];
            }

            if (isset($score['reason'])) {
                $data['reason'] = $score['reason'];
            }

            return $data;
        }, $scores);

        $this->httpClient->put('v1/private/spans/feedback-scores', ['scores' => $formattedScores]);
    }

    /**
     * Update an existing experiment.
     *
     * @param string $id The experiment ID
     * @param string|null $name New name for the experiment
     * @param array<string, mixed>|null $metadata Metadata to update
     *
     * @throws InvalidArgumentException If id is empty
     */
    public function updateExperiment(
        string $id,
        ?string $name = null,
        ?array $metadata = null,
    ): void {
        if (empty(trim($id))) {
            throw new InvalidArgumentException('Experiment ID cannot be empty');
        }

        $data = [];
        if ($name !== null) {
            $data['name'] = $name;
        }
        if ($metadata !== null) {
            $data['metadata'] = $metadata;
        }

        $this->httpClient->patch("v1/private/experiments/{$id}", $data);
    }

    /**
     * Get an experiment by ID.
     *
     * @param string $id The experiment ID
     *
     * @throws InvalidArgumentException If id is empty
     * @throws OpikException If the experiment is not found
     *
     * @return Experiment The experiment
     */
    public function getExperimentById(string $id): Experiment
    {
        if (empty(trim($id))) {
            throw new InvalidArgumentException('Experiment ID cannot be empty');
        }

        $response = $this->httpClient->get("v1/private/experiments/{$id}");

        return new Experiment(
            httpClient: $this->httpClient,
            id: $response['id'],
            name: $response['name'],
            datasetName: $response['dataset_name'],
            datasetId: $response['dataset_id'] ?? null,
        );
    }

    /**
     * Get project by ID.
     *
     * @param string $id The project ID
     *
     * @throws InvalidArgumentException If id is empty
     *
     * @return array<string, mixed> The project data
     */
    public function getProject(string $id): array
    {
        if (empty(trim($id))) {
            throw new InvalidArgumentException('Project ID cannot be empty');
        }

        return $this->httpClient->get("v1/private/projects/{$id}");
    }

    /**
     * Get the URL for a project in the current workspace.
     *
     * @param string|null $projectName Project name (uses default if not provided)
     *
     * @return string The project URL
     */
    public function getProjectUrl(?string $projectName = null): string
    {
        $project = $projectName ?? $this->config->projectName ?? 'Default Project';
        $baseUrl = rtrim($this->config->baseUrl, '/');
        $baseUrl = preg_replace('#/api/?$#', '', $baseUrl);

        $workspace = $this->config->workspace ?? 'default';

        return "{$baseUrl}/{$workspace}/projects/{$project}/traces";
    }

    /**
     * Get all datasets with pagination.
     *
     * @param int $page Page number (1-based)
     * @param int $size Page size
     *
     * @return array<int, Dataset> List of datasets
     */
    public function getDatasets(int $page = 1, int $size = 100): array
    {
        $response = $this->httpClient->get('v1/private/datasets', [
            'page' => $page,
            'size' => $size,
        ]);

        return array_map(
            fn (array $dataset) => new Dataset(
                httpClient: $this->httpClient,
                id: $dataset['id'],
                name: $dataset['name'],
                description: $dataset['description'] ?? null,
            ),
            $response['content'] ?? [],
        );
    }

    /**
     * Get all prompts with pagination.
     *
     * @param int $page Page number (1-based)
     * @param int $size Page size
     *
     * @return array<int, Prompt> List of prompts
     */
    public function getPrompts(int $page = 1, int $size = 100): array
    {
        $response = $this->httpClient->get('v1/private/prompts', [
            'page' => $page,
            'size' => $size,
        ]);

        return array_map(
            fn (array $prompt) => new Prompt(
                httpClient: $this->httpClient,
                id: $prompt['id'],
                name: $prompt['name'],
            ),
            $response['content'] ?? [],
        );
    }

    /**
     * Search prompts with optional filtering.
     *
     * @param string|null $name Filter by prompt name (partial match)
     * @param int $page Page number (1-based)
     * @param int $size Page size
     *
     * @return array<int, Prompt> List of matching prompts
     */
    public function searchPrompts(?string $name = null, int $page = 1, int $size = 100): array
    {
        $query = [
            'page' => $page,
            'size' => $size,
        ];

        if ($name !== null) {
            $query['name'] = $name;
        }

        $response = $this->httpClient->get('v1/private/prompts', $query);

        return array_map(
            fn (array $prompt) => new Prompt(
                httpClient: $this->httpClient,
                id: $prompt['id'],
                name: $prompt['name'],
            ),
            $response['content'] ?? [],
        );
    }

    /**
     * Delete prompts in batch.
     *
     * @param array<int, string> $ids List of prompt IDs to delete
     *
     * @throws InvalidArgumentException If ids array is empty
     */
    public function deletePrompts(array $ids): void
    {
        if (empty($ids)) {
            throw new InvalidArgumentException('Prompt IDs array cannot be empty');
        }

        $this->httpClient->post('v1/private/prompts/delete', ['ids' => $ids]);
    }

    /**
     * Get all versions of a prompt.
     *
     * @param string $name The prompt name
     * @param int $page Page number (1-based)
     * @param int $size Page size
     *
     * @throws InvalidArgumentException If name is empty
     * @throws OpikException If the prompt is not found
     *
     * @return array<int, array<string, mixed>> List of prompt versions
     */
    public function getPromptHistory(string $name, int $page = 1, int $size = 100): array
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Prompt name cannot be empty');
        }

        $prompt = $this->getPrompt($name);

        $response = $this->httpClient->get("v1/private/prompts/{$prompt->id}/versions", [
            'page' => $page,
            'size' => $size,
        ]);

        return $response['content'] ?? [];
    }

    /**
     * Delete a feedback score from a trace.
     *
     * @param string $traceId The trace ID
     * @param string $name The feedback score name
     *
     * @throws InvalidArgumentException If traceId or name is empty
     */
    public function deleteTraceFeedbackScore(string $traceId, string $name): void
    {
        if (empty(trim($traceId))) {
            throw new InvalidArgumentException('Trace ID cannot be empty');
        }

        if (empty(trim($name))) {
            throw new InvalidArgumentException('Feedback score name cannot be empty');
        }

        $this->httpClient->post("v1/private/traces/{$traceId}/feedback-scores/delete", ['name' => $name]);
    }

    /**
     * Delete a feedback score from a span.
     *
     * @param string $spanId The span ID
     * @param string $name The feedback score name
     *
     * @throws InvalidArgumentException If spanId or name is empty
     */
    public function deleteSpanFeedbackScore(string $spanId, string $name): void
    {
        if (empty(trim($spanId))) {
            throw new InvalidArgumentException('Span ID cannot be empty');
        }

        if (empty(trim($name))) {
            throw new InvalidArgumentException('Feedback score name cannot be empty');
        }

        $this->httpClient->post("v1/private/spans/{$spanId}/feedback-scores/delete", ['name' => $name]);
    }

    /**
     * Flush all pending messages to the API.
     *
     * Call this to ensure all traces and spans are sent before the script ends.
     * Note: Messages are automatically flushed on shutdown.
     */
    public function flush(): void
    {
        $this->batchQueue->flush();
    }

    /**
     * Get the current configuration.
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Get the batch queue (for advanced usage).
     */
    public function getBatchQueue(): BatchQueue
    {
        return $this->batchQueue;
    }

    /**
     * Validate the configuration and throw if invalid.
     *
     * @throws ConfigurationException If required configuration is missing
     */
    private function validateConfig(): void
    {
        if ($this->config->requiresAuthentication() && $this->config->apiKey === null) {
            throw new ConfigurationException(
                'API key is required for cloud deployment. ' .
                'Set OPIK_API_KEY environment variable or pass apiKey to constructor.',
            );
        }

        if ($this->config->requiresAuthentication() && $this->config->workspace === null) {
            throw new ConfigurationException(
                'Workspace is required for cloud deployment. ' .
                'Set OPIK_WORKSPACE environment variable or pass workspace to constructor.',
            );
        }
    }
}
