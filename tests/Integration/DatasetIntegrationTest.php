<?php

declare(strict_types=1);

namespace Opik\Tests\Integration;

use Opik\Dataset\DatasetItem;
use Opik\OpikClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Integration tests for dataset operations against Opik Cloud.
 *
 * These tests require the following environment variables:
 * - OPIK_API_KEY: API key for authentication
 * - OPIK_WORKSPACE: Workspace name
 */
final class DatasetIntegrationTest extends TestCase
{
    private ?OpikClient $client = null;

    private string $datasetName;

    protected function setUp(): void
    {
        $apiKey = getenv('OPIK_API_KEY');
        $workspace = getenv('OPIK_WORKSPACE');

        if ($apiKey === false || $apiKey === '') {
            self::markTestSkipped('OPIK_API_KEY environment variable not set');
        }

        if ($workspace === false || $workspace === '') {
            self::markTestSkipped('OPIK_WORKSPACE environment variable not set');
        }

        $this->datasetName = 'php-sdk-test-dataset-' . uniqid();

        $this->client = new OpikClient(
            apiKey: $apiKey,
            workspace: $workspace,
        );
    }

    protected function tearDown(): void
    {
        if ($this->client !== null) {
            try {
                $this->client->deleteDataset($this->datasetName);
            } catch (Throwable) {
                // Ignore cleanup errors
            }
        }
    }

    #[Test]
    public function shouldCreateAndRetrieveDataset(): void
    {
        self::assertNotNull($this->client);

        $dataset = $this->client->createDataset(
            name: $this->datasetName,
            description: 'Test dataset for PHP SDK integration tests',
        );

        self::assertSame($this->datasetName, $dataset->name);
        self::assertSame('Test dataset for PHP SDK integration tests', $dataset->description);
        self::assertNotEmpty($dataset->id);

        // Retrieve and verify
        $retrieved = $this->client->getDataset($this->datasetName);
        self::assertSame($dataset->id, $retrieved->id);
        self::assertSame($this->datasetName, $retrieved->name);
    }

    #[Test]
    public function shouldInsertAndRetrieveDatasetItems(): void
    {
        self::assertNotNull($this->client);

        $dataset = $this->client->createDataset(name: $this->datasetName);

        // Insert items
        $dataset->insert([
            new DatasetItem(data: ['input' => 'What is PHP?', 'expected_output' => 'A programming language']),
            new DatasetItem(data: ['input' => 'What is Python?', 'expected_output' => 'A programming language']),
        ]);

        // Retrieve and verify
        $items = $dataset->getItems();
        self::assertCount(2, $items);

        $inputs = array_map(fn (DatasetItem $item) => $item->get('input'), $items);
        self::assertContains('What is PHP?', $inputs);
        self::assertContains('What is Python?', $inputs);
    }

    #[Test]
    public function shouldInsertItemsFromArrays(): void
    {
        self::assertNotNull($this->client);

        $dataset = $this->client->createDataset(name: $this->datasetName);

        // Insert items as arrays with data field
        $dataset->insert([
            ['data' => ['input' => 'Test input 1', 'source' => 'test']],
            ['data' => ['input' => 'Test input 2', 'expected_output' => 'Expected 2']],
        ]);

        $items = $dataset->getItems();
        self::assertCount(2, $items);
    }

    #[Test]
    public function shouldDeleteDatasetItems(): void
    {
        self::assertNotNull($this->client);

        $dataset = $this->client->createDataset(name: $this->datasetName);

        // Insert items
        $dataset->insert([
            new DatasetItem(data: ['input' => 'Item to keep']),
            new DatasetItem(data: ['input' => 'Item to delete']),
        ]);

        $items = $dataset->getItems();
        self::assertCount(2, $items);

        // Find and delete one item
        $itemToDelete = null;
        foreach ($items as $item) {
            if ($item->get('input') === 'Item to delete') {
                $itemToDelete = $item;
                break;
            }
        }
        self::assertNotNull($itemToDelete);

        $dataset->delete([$itemToDelete->id]);

        // Verify deletion
        $remainingItems = $dataset->getItems();
        self::assertCount(1, $remainingItems);
        self::assertSame('Item to keep', $remainingItems[0]->get('input'));
    }

    #[Test]
    public function shouldClearDataset(): void
    {
        self::assertNotNull($this->client);

        $dataset = $this->client->createDataset(name: $this->datasetName);

        // Insert items
        $dataset->insert([
            new DatasetItem(data: ['input' => 'Item 1']),
            new DatasetItem(data: ['input' => 'Item 2']),
            new DatasetItem(data: ['input' => 'Item 3']),
        ]);

        self::assertCount(3, $dataset->getItems());

        // Clear all items
        $dataset->clear();

        // Verify cleared
        self::assertCount(0, $dataset->getItems());
    }

    #[Test]
    public function shouldGetOrCreateDataset(): void
    {
        self::assertNotNull($this->client);

        // Create new dataset
        $dataset1 = $this->client->getOrCreateDataset(
            name: $this->datasetName,
            description: 'Original description',
        );

        self::assertNotEmpty($dataset1->id);

        // Get existing dataset (should not create new)
        $dataset2 = $this->client->getOrCreateDataset(
            name: $this->datasetName,
            description: 'Different description',
        );

        self::assertSame($dataset1->id, $dataset2->id);
    }

    #[Test]
    public function shouldListDatasets(): void
    {
        self::assertNotNull($this->client);

        // Create a dataset
        $this->client->createDataset(name: $this->datasetName);

        // List datasets (returns array of Dataset objects)
        $datasets = $this->client->getDatasets();

        self::assertIsArray($datasets);

        // Our dataset should be in the list
        $found = false;
        foreach ($datasets as $dataset) {
            if ($dataset->name === $this->datasetName) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Created dataset should be in the list');
    }
}
