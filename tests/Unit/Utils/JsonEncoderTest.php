<?php

declare(strict_types=1);

namespace Opik\Tests\Unit\Utils;

use DateTimeImmutable;
use Opik\Utils\JsonEncoder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class JsonEncoderTest extends TestCase
{
    #[Test]
    public function shouldEncodeSimpleArray(): void
    {
        $data = ['key' => 'value', 'number' => 42];

        $result = JsonEncoder::encode($data);

        self::assertSame('{"key":"value","number":42}', $result);
    }

    #[Test]
    public function shouldEncodeNestedArray(): void
    {
        $data = ['outer' => ['inner' => 'value']];

        $result = JsonEncoder::encode($data);

        self::assertSame('{"outer":{"inner":"value"}}', $result);
    }

    #[Test]
    public function shouldDecodeJsonString(): void
    {
        $json = '{"key":"value","number":42}';

        $result = JsonEncoder::decode($json);

        self::assertSame(['key' => 'value', 'number' => 42], $result);
    }

    #[Test]
    public function shouldHandleDateTimeObjects(): void
    {
        $dateTime = new DateTimeImmutable('2024-01-15T10:30:00+00:00');
        $data = ['date' => $dateTime];

        $result = JsonEncoder::encode($data);

        self::assertStringContainsString('2024-01-15', $result);
    }

    #[Test]
    public function shouldPreserveUnicodeCharacters(): void
    {
        $data = ['message' => 'Hello, 世界!'];

        $result = JsonEncoder::encode($data);

        self::assertStringContainsString('世界', $result);
    }

    #[Test]
    public function shouldNotEscapeSlashes(): void
    {
        $data = ['url' => 'https://example.com/path'];

        $result = JsonEncoder::encode($data);

        self::assertStringContainsString('https://example.com/path', $result);
    }
}
