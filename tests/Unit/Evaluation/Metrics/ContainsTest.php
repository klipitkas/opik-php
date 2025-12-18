<?php

declare(strict_types=1);

namespace Opik\Tests\Unit\Evaluation\Metrics;

use Opik\Evaluation\Metrics\Contains;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContainsTest extends TestCase
{
    #[Test]
    #[DataProvider('caseSensitiveCases')]
    public function shouldMatchCaseSensitive(string $output, string $expected, float $expectedScore): void
    {
        $result = (new Contains())->score(['output' => $output, 'expected' => $expected]);

        self::assertSame($expectedScore, $result->value);
    }

    /** @return iterable<string, array{string, string, float}> */
    public static function caseSensitiveCases(): iterable
    {
        yield 'contains substring' => ['hello world', 'world', 1.0];
        yield 'does not contain' => ['hello world', 'goodbye', 0.0];
        yield 'case mismatch' => ['Hello World', 'hello', 0.0];
        yield 'exact match' => ['hello', 'hello', 1.0];
        yield 'empty expected' => ['hello', '', 1.0];
    }

    #[Test]
    public function shouldMatchCaseInsensitive(): void
    {
        $result = (new Contains(caseSensitive: false))->score([
            'output' => 'Hello World',
            'expected' => 'hello',
        ]);

        self::assertSame(1.0, $result->value);
    }

    #[Test]
    public function shouldUseDefaultName(): void
    {
        $result = (new Contains())->score(['output' => 'test', 'expected' => 'test']);

        self::assertSame('contains', $result->name);
        self::assertStringContainsString('contains the expected', $result->reason);
    }

    #[Test]
    public function shouldUseCustomName(): void
    {
        $result = (new Contains('custom'))->score(['output' => 'test', 'expected' => 'test']);

        self::assertSame('custom', $result->name);
    }
}
