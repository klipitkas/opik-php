<?php

declare(strict_types=1);

namespace Opik\Tests\Unit\Evaluation\Metrics;

use Opik\Evaluation\Metrics\Equals;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EqualsTest extends TestCase
{
    #[Test]
    #[DataProvider('strictEqualityCases')]
    public function shouldCalculateStrictEquality(mixed $output, mixed $expected, float $expectedScore): void
    {
        $result = (new Equals())->score(['output' => $output, 'expected' => $expected]);

        self::assertSame($expectedScore, $result->value);
    }

    /** @return iterable<string, array{mixed, mixed, float}> */
    public static function strictEqualityCases(): iterable
    {
        yield 'equal strings' => ['hello', 'hello', 1.0];
        yield 'different strings' => ['hello', 'world', 0.0];
        yield 'string vs int' => ['1', 1, 0.0];
        yield 'bool vs int' => [true, 1, 0.0];
        yield 'arrays match' => [['a' => 1], ['a' => 1], 1.0];
        yield 'arrays differ' => [['a' => 1], ['a' => 2], 0.0];
        yield 'null values' => [null, null, 1.0];
        yield 'boolean values' => [true, true, 1.0];
        yield 'float values' => [3.14, 3.14, 1.0];
    }

    #[Test]
    #[DataProvider('looseEqualityCases')]
    public function shouldCalculateLooseEquality(mixed $output, mixed $expected, float $expectedScore): void
    {
        $result = (new Equals(strict: false))->score(['output' => $output, 'expected' => $expected]);

        self::assertSame($expectedScore, $result->value);
    }

    /** @return iterable<string, array{mixed, mixed, float}> */
    public static function looseEqualityCases(): iterable
    {
        yield 'string vs int' => ['1', 1, 1.0];
        yield 'bool vs int' => [true, 1, 1.0];
    }

    #[Test]
    public function shouldIndicateComparisonTypeInReason(): void
    {
        $strictResult = (new Equals())->score(['output' => 'test', 'expected' => 'test']);
        $looseResult = (new Equals(strict: false))->score(['output' => 'test', 'expected' => 'test']);

        self::assertStringContainsString('strict', $strictResult->reason);
        self::assertStringContainsString('loose', $looseResult->reason);
    }

    #[Test]
    public function shouldUseDefaultName(): void
    {
        $result = (new Equals())->score(['output' => 'test', 'expected' => 'test']);

        self::assertSame('equals', $result->name);
    }

    #[Test]
    public function shouldUseCustomName(): void
    {
        $result = (new Equals(name: 'custom'))->score(['output' => 'test', 'expected' => 'test']);

        self::assertSame('custom', $result->name);
    }

    #[Test]
    public function shouldHandleMissingKeys(): void
    {
        $result = (new Equals())->score([]);

        self::assertSame(1.0, $result->value);
    }
}
