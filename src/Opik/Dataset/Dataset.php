<?php

declare(strict_types=1);

namespace Opik\Dataset;

use InvalidArgumentException;
use Opik\Api\HttpClientInterface;

final class Dataset
{
    /** @var array<int, DatasetItem> */
    private array $items = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $description = null,
    ) {
    }

    /**
     * @param array<int, DatasetItem>|array<int, array<string, mixed>> $items
     */
    public function insert(array $items): self
    {
        $datasetItems = array_map(
            static fn (DatasetItem|array $item) => $item instanceof DatasetItem
                ? $item
                : DatasetItem::fromArray($item),
            $items,
        );

        $this->httpClient->put('v1/private/datasets/items', [
            'dataset_name' => $this->name,
            'items' => array_map(
                static fn (DatasetItem $item) => $item->toArray(),
                $datasetItems,
            ),
        ]);

        $this->items = array_merge($this->items, $datasetItems);

        return $this;
    }

    /**
     * Update existing items in the dataset.
     * You need to provide the full item object as it will override what has been supplied previously.
     *
     * @param array<int, DatasetItem>|array<int, array<string, mixed>> $items Items to update (must have IDs)
     *
     * @throws InvalidArgumentException If any item is missing an ID
     */
    public function update(array $items): self
    {
        if ($items === []) {
            return $this;
        }

        foreach ($items as $index => $item) {
            $itemArray = $item instanceof DatasetItem ? ['id' => $item->id] : $item;
            if (!isset($itemArray['id']) || empty($itemArray['id'])) {
                throw new InvalidArgumentException(
                    "Dataset item at index {$index} is missing an 'id' field. " .
                    'Update operations require all items to have IDs.',
                );
            }
        }

        return $this->insert($items);
    }

    /**
     * Delete items from the dataset by their IDs.
     *
     * @param array<int, string> $itemIds
     */
    public function delete(array $itemIds): self
    {
        $this->httpClient->post('v1/private/datasets/items/delete', [
            'item_ids' => $itemIds,
        ]);

        $this->items = array_filter(
            $this->items,
            static fn (DatasetItem $item) => !\in_array($item->id, $itemIds, true),
        );

        return $this;
    }

    /**
     * @return array<int, DatasetItem>
     */
    public function getItems(int $page = 1, int $size = 100): array
    {
        $response = $this->httpClient->get("v1/private/datasets/{$this->id}/items", [
            'page' => $page,
            'size' => $size,
        ]);

        return array_map(
            static fn (array $item) => DatasetItem::fromArray($item),
            $response['content'] ?? [],
        );
    }

    public function clear(): self
    {
        $this->httpClient->post('v1/private/datasets/items/delete', [
            'dataset_id' => $this->id,
        ]);

        $this->items = [];

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
        ];
    }
}
