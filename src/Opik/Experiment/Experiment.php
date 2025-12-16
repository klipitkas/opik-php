<?php

declare(strict_types=1);

namespace Opik\Experiment;

use Opik\Api\HttpClientInterface;

final class Experiment
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $datasetName = null,
        public readonly ?string $datasetId = null,
    ) {
    }

    /**
     * @param array<int, ExperimentItem> $items
     */
    public function logItems(array $items): self
    {
        $this->httpClient->post('v1/private/experiments/items', [
            'experiment_items' => array_map(
                fn (ExperimentItem $item) => array_merge(
                    $item->toArray(),
                    ['experiment_id' => $this->id],
                ),
                $items,
            ),
        ]);

        return $this;
    }

    /**
     * @return array<int, ExperimentItem>
     */
    public function getItems(int $page = 1, int $size = 100): array
    {
        $response = $this->httpClient->get("v1/private/experiments/{$this->id}/items", [
            'page' => $page,
            'size' => $size,
        ]);

        return array_map(
            static fn (array $item) => ExperimentItem::fromArray($item),
            $response['content'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'dataset_name' => $this->datasetName,
            'dataset_id' => $this->datasetId,
        ];
    }
}
