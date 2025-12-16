<?php

declare(strict_types=1);

namespace Opik\Experiment;

use Opik\Utils\IdGenerator;

final class ExperimentItem
{
    public readonly string $id;

    public readonly string $datasetItemId;

    public readonly ?string $traceId;

    /** @var array<string, mixed>|null */
    public readonly ?array $output;

    /** @var array<int, array<string, mixed>>|null */
    public readonly ?array $feedbackScores;

    /**
     * @param array<string, mixed>|null $output
     * @param array<int, array<string, mixed>>|null $feedbackScores
     */
    public function __construct(
        string $datasetItemId,
        ?string $traceId = null,
        ?string $id = null,
        ?array $output = null,
        ?array $feedbackScores = null,
    ) {
        $this->datasetItemId = $datasetItemId;
        $this->traceId = $traceId;
        $this->id = $id ?? IdGenerator::uuid();
        $this->output = $output;
        $this->feedbackScores = $feedbackScores;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            datasetItemId: $data['dataset_item_id'],
            traceId: $data['trace_id'] ?? null,
            id: $data['id'] ?? null,
            output: $data['output'] ?? null,
            feedbackScores: $data['feedback_scores'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'dataset_item_id' => $this->datasetItemId,
        ];

        if ($this->traceId !== null) {
            $data['trace_id'] = $this->traceId;
        }

        if ($this->output !== null) {
            $data['output'] = $this->output;
        }

        if ($this->feedbackScores !== null) {
            $data['feedback_scores'] = $this->feedbackScores;
        }

        return $data;
    }
}
