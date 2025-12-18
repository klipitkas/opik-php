<?php

declare(strict_types=1);

namespace Opik\Dataset;

use InvalidArgumentException;
use JsonException;
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
     * Insert items from a JSON string array into the dataset.
     *
     * @param string $jsonArray JSON string of format: "[{...}, {...}, {...}]" where every object is transformed into a dataset item
     * @param array<string, string> $keysMapping Optional dictionary that maps JSON keys to dataset item field names (e.g., ['Expected output' => 'expected_output'])
     * @param array<int, string> $ignoreKeys Optional array of keys that should be ignored when constructing dataset items
     *
     * @throws InvalidArgumentException If the JSON is invalid or not an array
     */
    public function insertFromJson(
        string $jsonArray,
        array $keysMapping = [],
        array $ignoreKeys = [],
    ): self {
        try {
            $parsedItems = json_decode($jsonArray, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException("Invalid JSON: {$e->getMessage()}", 0, $e);
        }

        if (! \is_array($parsedItems)) {
            throw new InvalidArgumentException(
                \sprintf('JSON must be an array, got %s', \gettype($parsedItems)),
            );
        }

        if ($parsedItems === []) {
            return $this;
        }

        // Check if it's a sequential array (list) vs associative array (object)
        if (! array_is_list($parsedItems)) {
            throw new InvalidArgumentException(
                'JSON must be an array of objects, got a single object',
            );
        }

        // Validate all items are objects and transform them
        $transformedItems = [];
        foreach ($parsedItems as $index => $item) {
            if (! \is_array($item)) {
                throw new InvalidArgumentException(
                    \sprintf('Item at index %d must be an object, got %s', $index, \gettype($item)),
                );
            }

            $transformedItem = [];
            foreach ($item as $key => $value) {
                if (\in_array($key, $ignoreKeys, true)) {
                    continue;
                }

                $mappedKey = $keysMapping[$key] ?? $key;
                $transformedItem[$mappedKey] = $value;
            }

            $transformedItems[] = new DatasetItem(data: $transformedItem);
        }

        return $this->insert($transformedItems);
    }

    /**
     * Convert the dataset items to a JSON string.
     *
     * @param array<string, string> $keysMapping Optional dictionary that maps dataset item field names to output JSON keys
     *
     * @return string A JSON string representation of all items in the dataset
     */
    public function toJson(array $keysMapping = []): string
    {
        $items = $this->getItems();

        $mappedItems = [];
        foreach ($items as $item) {
            $itemData = $item->getContent();

            if ($keysMapping !== []) {
                $mappedData = [];
                foreach ($itemData as $key => $value) {
                    $mappedKey = $keysMapping[$key] ?? $key;
                    $mappedData[$mappedKey] = $value;
                }
                $itemData = $mappedData;
            }

            $mappedItems[] = $itemData;
        }

        return json_encode($mappedItems, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
