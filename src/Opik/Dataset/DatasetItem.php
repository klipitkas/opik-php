<?php

declare(strict_types=1);

namespace Opik\Dataset;

use Opik\Utils\IdGenerator;

final class DatasetItem
{
    public readonly string $id;

    /** @var array<string, mixed> */
    private readonly array $data;

    public readonly ?string $traceId;

    public readonly ?string $spanId;

    public readonly DatasetItemSource $source;

    /**
     * Create a dataset item with flexible schema.
     *
     * Common fields like 'input', 'expected_output', 'metadata' can be passed directly,
     * or use the $data parameter for arbitrary fields (matching Python/TypeScript SDKs).
     *
     * @param array<string, mixed>|null $input Standard input field
     * @param array<string, mixed>|null $expectedOutput Standard expected output field
     * @param array<string, mixed>|null $metadata Standard metadata field
     * @param array<string, mixed> $data Arbitrary data fields (flexible schema)
     */
    public function __construct(
        ?string $id = null,
        ?array $input = null,
        ?array $expectedOutput = null,
        ?array $metadata = null,
        ?string $traceId = null,
        ?string $spanId = null,
        DatasetItemSource $source = DatasetItemSource::SDK,
        array $data = [],
    ) {
        $this->traceId = $traceId;
        $this->spanId = $spanId;
        $this->source = $source;
        $this->id = $id ?? IdGenerator::uuid();

        // Build the data array from explicit fields and arbitrary data
        $builtData = $data;

        if ($input !== null) {
            $builtData['input'] = $input;
        }

        if ($expectedOutput !== null) {
            $builtData['expected_output'] = $expectedOutput;
        }

        if ($metadata !== null) {
            $builtData['metadata'] = $metadata;
        }

        $this->data = $builtData;
    }

    /**
     * Get the content data (all arbitrary fields).
     *
     * @return array<string, mixed>
     */
    public function getContent(): array
    {
        return $this->data;
    }

    /**
     * Get a specific field from the data.
     */
    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Get the input field (convenience accessor).
     *
     * @return array<string, mixed>|null
     */
    public function getInput(): ?array
    {
        $input = $this->data['input'] ?? null;

        return \is_array($input) ? $input : null;
    }

    /**
     * Get the expected output field (convenience accessor).
     *
     * @return array<string, mixed>|null
     */
    public function getExpectedOutput(): ?array
    {
        $output = $this->data['expected_output'] ?? null;

        return \is_array($output) ? $output : null;
    }

    /**
     * Get the metadata field (convenience accessor).
     *
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        $metadata = $this->data['metadata'] ?? null;

        return \is_array($metadata) ? $metadata : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        // Handle API response format where content is in 'data' field
        $content = $data['data'] ?? [];

        return new self(
            id: $data['id'] ?? null,
            traceId: $data['trace_id'] ?? null,
            spanId: $data['span_id'] ?? null,
            source: isset($data['source']) ? DatasetItemSource::from($data['source']) : DatasetItemSource::SDK,
            data: \is_array($content) ? $content : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        // API format: data field contains the actual content
        $apiData = [
            'id' => $this->id,
            'source' => $this->source->value,
            'data' => $this->data,
        ];

        if ($this->traceId !== null) {
            $apiData['trace_id'] = $this->traceId;
        }

        if ($this->spanId !== null) {
            $apiData['span_id'] = $this->spanId;
        }

        return $apiData;
    }
}
