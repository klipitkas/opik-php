<?php

declare(strict_types=1);

namespace Opik\Dataset;

use Opik\Utils\IdGenerator;

final readonly class DatasetItem
{
    public string $id;

    /**
     * @param array<string, mixed>|null $input
     * @param array<string, mixed>|null $expectedOutput
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        ?string $id = null,
        public ?array $input = null,
        public ?array $expectedOutput = null,
        public ?array $metadata = null,
        public ?string $traceId = null,
        public ?string $spanId = null,
        public DatasetItemSource $source = DatasetItemSource::SDK,
    ) {
        $this->id = $id ?? IdGenerator::uuid();
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
            input: $content['input'] ?? null,
            expectedOutput: $content['expected_output'] ?? null,
            metadata: $content['metadata'] ?? null,
            traceId: $data['trace_id'] ?? null,
            spanId: $data['span_id'] ?? null,
            source: isset($data['source']) ? DatasetItemSource::from($data['source']) : DatasetItemSource::SDK,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        // Build the data content (flexible schema)
        $dataContent = [];
        
        if ($this->input !== null) {
            $dataContent['input'] = $this->input;
        }

        if ($this->expectedOutput !== null) {
            $dataContent['expected_output'] = $this->expectedOutput;
        }

        if ($this->metadata !== null) {
            $dataContent['metadata'] = $this->metadata;
        }

        // API format: data field contains the actual content
        $apiData = [
            'id' => $this->id,
            'source' => $this->source->value,
            'data' => $dataContent,
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
