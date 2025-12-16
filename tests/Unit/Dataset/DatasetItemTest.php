<?php

declare(strict_types=1);

namespace Opik\Tests\Unit\Dataset;

use Opik\Dataset\DatasetItem;
use Opik\Dataset\DatasetItemSource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DatasetItemTest extends TestCase
{
    #[Test]
    public function shouldCreateItemWithInput(): void
    {
        $item = new DatasetItem(
            input: ['question' => 'What is PHP?'],
        );

        self::assertNotEmpty($item->id);
        self::assertSame(['question' => 'What is PHP?'], $item->getInput());
        self::assertSame(DatasetItemSource::SDK, $item->source);
    }

    #[Test]
    public function shouldCreateItemWithExpectedOutput(): void
    {
        $item = new DatasetItem(
            input: ['question' => 'What is PHP?'],
            expectedOutput: ['answer' => 'A programming language'],
        );

        self::assertSame(['answer' => 'A programming language'], $item->getExpectedOutput());
    }

    #[Test]
    public function shouldCreateItemWithCustomId(): void
    {
        $item = new DatasetItem(
            id: 'custom-id-123',
            input: ['data' => 'test'],
        );

        self::assertSame('custom-id-123', $item->id);
    }

    #[Test]
    public function shouldCreateItemFromArray(): void
    {
        $data = [
            'id' => 'item-id',
            'data' => [
                'input' => ['key' => 'value'],
                'expected_output' => ['result' => 'expected'],
                'metadata' => ['source' => 'test'],
            ],
            'source' => 'manual',
        ];

        $item = DatasetItem::fromArray($data);

        self::assertSame('item-id', $item->id);
        self::assertSame(['key' => 'value'], $item->getInput());
        self::assertSame(['result' => 'expected'], $item->getExpectedOutput());
        self::assertSame(['source' => 'test'], $item->getMetadata());
        self::assertSame(DatasetItemSource::MANUAL, $item->source);
    }

    #[Test]
    public function shouldConvertToArray(): void
    {
        $item = new DatasetItem(
            id: 'item-123',
            input: ['question' => 'test'],
            expectedOutput: ['answer' => 'response'],
            metadata: ['key' => 'value'],
            traceId: 'trace-456',
            source: DatasetItemSource::TRACE,
        );

        $array = $item->toArray();

        self::assertSame('item-123', $array['id']);
        self::assertSame(['question' => 'test'], $array['data']['input']);
        self::assertSame(['answer' => 'response'], $array['data']['expected_output']);
        self::assertSame(['key' => 'value'], $array['data']['metadata']);
        self::assertSame('trace-456', $array['trace_id']);
        self::assertSame('trace', $array['source']);
    }

    #[Test]
    public function shouldOmitNullFieldsInArray(): void
    {
        $item = new DatasetItem(
            input: ['data' => 'test'],
        );

        $array = $item->toArray();

        self::assertArrayNotHasKey('expected_output', $array['data']);
        self::assertArrayNotHasKey('metadata', $array['data']);
        self::assertArrayNotHasKey('trace_id', $array);
    }

    #[Test]
    public function shouldCreateItemWithArbitraryFields(): void
    {
        $item = new DatasetItem(
            data: [
                'prompt' => 'Write a poem',
                'context' => 'Creative writing',
                'difficulty' => 'medium',
                'tags' => ['poetry', 'creative'],
            ],
        );

        self::assertNotEmpty($item->id);
        self::assertSame('Write a poem', $item->get('prompt'));
        self::assertSame('Creative writing', $item->get('context'));
        self::assertSame('medium', $item->get('difficulty'));
        self::assertSame(['poetry', 'creative'], $item->get('tags'));
        self::assertNull($item->getInput());
        self::assertNull($item->getExpectedOutput());
    }

    #[Test]
    public function shouldMergeStandardAndArbitraryFields(): void
    {
        $item = new DatasetItem(
            input: ['question' => 'What is AI?'],
            metadata: ['category' => 'tech'],
            data: [
                'custom_field' => 'custom_value',
                'priority' => 1,
            ],
        );

        $content = $item->getContent();

        self::assertSame(['question' => 'What is AI?'], $content['input']);
        self::assertSame(['category' => 'tech'], $content['metadata']);
        self::assertSame('custom_value', $content['custom_field']);
        self::assertSame(1, $content['priority']);
    }

    #[Test]
    public function shouldCreateItemFromArrayWithArbitraryFields(): void
    {
        $data = [
            'id' => 'arbitrary-item',
            'data' => [
                'prompt' => 'Generate code',
                'language' => 'php',
                'complexity' => 'high',
            ],
            'source' => 'sdk',
        ];

        $item = DatasetItem::fromArray($data);

        self::assertSame('arbitrary-item', $item->id);
        self::assertSame('Generate code', $item->get('prompt'));
        self::assertSame('php', $item->get('language'));
        self::assertSame('high', $item->get('complexity'));
        self::assertNull($item->getInput());
    }

    #[Test]
    public function shouldConvertArbitraryFieldsToArray(): void
    {
        $item = new DatasetItem(
            id: 'arb-123',
            data: [
                'prompt' => 'Test prompt',
                'settings' => ['temperature' => 0.7],
            ],
        );

        $array = $item->toArray();

        self::assertSame('arb-123', $array['id']);
        self::assertSame('Test prompt', $array['data']['prompt']);
        self::assertSame(['temperature' => 0.7], $array['data']['settings']);
        self::assertArrayNotHasKey('input', $array['data']);
    }

    #[Test]
    public function shouldGetContentReturnsAllFields(): void
    {
        $item = new DatasetItem(
            input: ['q' => 'test'],
            expectedOutput: ['a' => 'answer'],
            metadata: ['m' => 'meta'],
            data: ['extra' => 'field'],
        );

        $content = $item->getContent();

        self::assertCount(4, $content);
        self::assertArrayHasKey('input', $content);
        self::assertArrayHasKey('expected_output', $content);
        self::assertArrayHasKey('metadata', $content);
        self::assertArrayHasKey('extra', $content);
    }
}
