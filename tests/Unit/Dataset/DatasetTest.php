<?php

declare(strict_types=1);

namespace Opik\Tests\Unit\Dataset;

use InvalidArgumentException;
use Opik\Api\HttpClientInterface;
use Opik\Dataset\Dataset;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DatasetTest extends TestCase
{
    private HttpClientInterface&\PHPUnit\Framework\MockObject\MockObject $httpClient;

    private Dataset $dataset;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->dataset = new Dataset(
            httpClient: $this->httpClient,
            id: 'dataset-123',
            name: 'test-dataset',
            description: 'A test dataset',
        );
    }

    #[Test]
    public function shouldInsertFromJsonWithValidArray(): void
    {
        $json = '[{"input": {"question": "What is PHP?"}, "expected_output": {"answer": "A language"}}]';

        $this->httpClient->expects(self::once())
            ->method('put')
            ->with('v1/private/datasets/items', self::callback(function ($data) {
                return $data['dataset_name'] === 'test-dataset'
                    && \count($data['items']) === 1
                    && $data['items'][0]['data']['input'] === ['question' => 'What is PHP?']
                    && $data['items'][0]['data']['expected_output'] === ['answer' => 'A language'];
            }));

        $result = $this->dataset->insertFromJson($json);

        self::assertSame($this->dataset, $result);
    }

    #[Test]
    public function shouldInsertFromJsonWithKeysMapping(): void
    {
        $json = '[{"Question": "What is PHP?", "Expected Answer": "A language"}]';

        $this->httpClient->expects(self::once())
            ->method('put')
            ->with('v1/private/datasets/items', self::callback(function ($data) {
                return $data['items'][0]['data']['input'] === 'What is PHP?'
                    && $data['items'][0]['data']['expected_output'] === 'A language';
            }));

        $this->dataset->insertFromJson($json, [
            'Question' => 'input',
            'Expected Answer' => 'expected_output',
        ]);
    }

    #[Test]
    public function shouldInsertFromJsonWithIgnoreKeys(): void
    {
        $json = '[{"input": "test", "internal_id": "abc123", "debug_info": "ignore me"}]';

        $this->httpClient->expects(self::once())
            ->method('put')
            ->with('v1/private/datasets/items', self::callback(function ($data) {
                return $data['items'][0]['data']['input'] === 'test'
                    && ! isset($data['items'][0]['data']['internal_id'])
                    && ! isset($data['items'][0]['data']['debug_info']);
            }));

        $this->dataset->insertFromJson($json, [], ['internal_id', 'debug_info']);
    }

    #[Test]
    public function shouldReturnSelfForEmptyJsonArray(): void
    {
        $this->httpClient->expects(self::never())->method('put');

        $result = $this->dataset->insertFromJson('[]');

        self::assertSame($this->dataset, $result);
    }

    #[Test]
    public function shouldThrowOnInvalidJson(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON');

        $this->dataset->insertFromJson('not valid json');
    }

    #[Test]
    public function shouldThrowWhenJsonIsNotArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JSON must be an array of objects, got a single object');

        $this->dataset->insertFromJson('{"key": "value"}');
    }

    #[Test]
    public function shouldThrowWhenItemIsNotObject(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Item at index 1 must be an object');

        $this->dataset->insertFromJson('[{"valid": "item"}, "not an object"]');
    }

    #[Test]
    public function shouldConvertToJsonWithItems(): void
    {
        $this->httpClient->expects(self::once())
            ->method('get')
            ->with('v1/private/datasets/dataset-123/items', ['page' => 1, 'size' => 100])
            ->willReturn([
                'content' => [
                    [
                        'id' => 'item-1',
                        'data' => ['input' => 'question 1', 'output' => 'answer 1'],
                        'source' => 'sdk',
                    ],
                    [
                        'id' => 'item-2',
                        'data' => ['input' => 'question 2', 'output' => 'answer 2'],
                        'source' => 'sdk',
                    ],
                ],
            ]);

        $json = $this->dataset->toJson();
        $decoded = json_decode($json, true);

        self::assertCount(2, $decoded);
        self::assertSame('question 1', $decoded[0]['input']);
        self::assertSame('answer 1', $decoded[0]['output']);
        self::assertSame('question 2', $decoded[1]['input']);
        self::assertSame('answer 2', $decoded[1]['output']);
    }

    #[Test]
    public function shouldConvertToJsonWithKeysMapping(): void
    {
        $this->httpClient->expects(self::once())
            ->method('get')
            ->willReturn([
                'content' => [
                    [
                        'id' => 'item-1',
                        'data' => ['input' => 'test', 'expected_output' => 'result'],
                        'source' => 'sdk',
                    ],
                ],
            ]);

        $json = $this->dataset->toJson([
            'input' => 'Question',
            'expected_output' => 'Expected Answer',
        ]);
        $decoded = json_decode($json, true);

        self::assertSame('test', $decoded[0]['Question']);
        self::assertSame('result', $decoded[0]['Expected Answer']);
        self::assertArrayNotHasKey('input', $decoded[0]);
        self::assertArrayNotHasKey('expected_output', $decoded[0]);
    }

    #[Test]
    public function shouldReturnEmptyArrayJsonWhenNoItems(): void
    {
        $this->httpClient->expects(self::once())
            ->method('get')
            ->willReturn(['content' => []]);

        $json = $this->dataset->toJson();

        self::assertSame('[]', $json);
    }

    #[Test]
    public function shouldPreserveUnicodeInJson(): void
    {
        $this->httpClient->expects(self::once())
            ->method('get')
            ->willReturn([
                'content' => [
                    [
                        'id' => 'item-1',
                        'data' => ['text' => 'Hello, World!'],
                        'source' => 'sdk',
                    ],
                ],
            ]);

        $json = $this->dataset->toJson();

        self::assertStringContainsString('Hello, World!', $json);
        self::assertStringNotContainsString('\u', $json);
    }
}
