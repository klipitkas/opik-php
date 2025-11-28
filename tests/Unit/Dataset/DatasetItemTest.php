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
        self::assertSame(['question' => 'What is PHP?'], $item->input);
        self::assertSame(DatasetItemSource::SDK, $item->source);
    }

    #[Test]
    public function shouldCreateItemWithExpectedOutput(): void
    {
        $item = new DatasetItem(
            input: ['question' => 'What is PHP?'],
            expectedOutput: ['answer' => 'A programming language'],
        );

        self::assertSame(['answer' => 'A programming language'], $item->expectedOutput);
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
            'input' => ['key' => 'value'],
            'expected_output' => ['result' => 'expected'],
            'metadata' => ['source' => 'test'],
            'source' => 'manual',
        ];

        $item = DatasetItem::fromArray($data);

        self::assertSame('item-id', $item->id);
        self::assertSame(['key' => 'value'], $item->input);
        self::assertSame(['result' => 'expected'], $item->expectedOutput);
        self::assertSame(['source' => 'test'], $item->metadata);
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
        self::assertSame(['question' => 'test'], $array['input']);
        self::assertSame(['answer' => 'response'], $array['expected_output']);
        self::assertSame(['key' => 'value'], $array['metadata']);
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

        self::assertArrayNotHasKey('expected_output', $array);
        self::assertArrayNotHasKey('metadata', $array);
        self::assertArrayNotHasKey('trace_id', $array);
    }
}
