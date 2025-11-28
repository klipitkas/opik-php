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
        return new self(
            id: $data['id'] ?? null,
            input: $data['input'] ?? null,
            expectedOutput: $data['expected_output'] ?? null,
            metadata: $data['metadata'] ?? null,
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
        $data = [
            'id' => $this->id,
            'source' => $this->source->value,
        ];

        if ($this->input !== null) {
            $data['input'] = $this->input;
        }

        if ($this->expectedOutput !== null) {
            $data['expected_output'] = $this->expectedOutput;
        }

        if ($this->metadata !== null) {
            $data['metadata'] = $this->metadata;
        }

        if ($this->traceId !== null) {
            $data['trace_id'] = $this->traceId;
        }

        if ($this->spanId !== null) {
            $data['span_id'] = $this->spanId;
        }

        return $data;
    }
}
