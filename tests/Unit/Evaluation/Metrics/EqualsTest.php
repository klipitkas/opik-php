<?php

declare(strict_types=1);

namespace Opik\Tests\Unit\Evaluation\Metrics;

use Opik\Evaluation\Metrics\Equals;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EqualsTest extends TestCase
{
    #[Test]
    public function shouldReturnOneForEqualStrings(): void
    {
        $metric = new Equals();

        $result = $metric->score([
            'output' => 'hello world',
            'expected' => 'hello world',
        ]);

        self::assertSame('equals', $result->name);
        self::assertSame(1.0, $result->value);
        self::assertStringContainsString('Equal', $result->reason);
    }

    #[Test]
    public function shouldReturnZeroForDifferentStrings(): void
    {
        $metric = new Equals();

        $result = $metric->score([
            'output' => 'hello',
            'expected' => 'world',
        ]);

        self::assertSame(0.0, $result->value);
        self::assertStringContainsString('Not equal', $result->reason);
    }

    #[Test]
    public function shouldUseStrictComparisonByDefault(): void
    {
        $metric = new Equals();

        // "1" (string) vs 1 (int) - strict comparison should fail
        $result = $metric->score([
            'output' => '1',
            'expected' => 1,
        ]);

        self::assertSame(0.0, $result->value);
        self::assertStringContainsString('strict', $result->reason);
    }

    #[Test]
    public function shouldUseLooseComparisonWhenSpecified(): void
    {
        $metric = new Equals(strict: false);

        // "1" (string) vs 1 (int) - loose comparison should pass
        $result = $metric->score([
            'output' => '1',
            'expected' => 1,
        ]);

        self::assertSame(1.0, $result->value);
        self::assertStringContainsString('loose', $result->reason);
    }

    #[Test]
    public function shouldHandleArraysWithStrictComparison(): void
    {
        $metric = new Equals();

        $result = $metric->score([
            'output' => ['a' => 1, 'b' => 2],
            'expected' => ['a' => 1, 'b' => 2],
        ]);

        self::assertSame(1.0, $result->value);
    }

    #[Test]
    public function shouldNotMatchDifferentArrays(): void
    {
        $metric = new Equals();

        $result = $metric->score([
            'output' => ['a' => 1],
            'expected' => ['a' => 2],
        ]);

        self::assertSame(0.0, $result->value);
    }

    #[Test]
    public function shouldUseCustomName(): void
    {
        $metric = new Equals(name: 'my_equality_check');

        $result = $metric->score([
            'output' => 'test',
            'expected' => 'test',
        ]);

        self::assertSame('my_equality_check', $result->name);
    }

    #[Test]
    public function shouldHandleNullValues(): void
    {
        $metric = new Equals();

        $result = $metric->score([
            'output' => null,
            'expected' => null,
        ]);

        self::assertSame(1.0, $result->value);
    }

    #[Test]
    public function shouldHandleBooleanValues(): void
    {
        $metric = new Equals();

        $result = $metric->score([
            'output' => true,
            'expected' => true,
        ]);

        self::assertSame(1.0, $result->value);
    }

    #[Test]
    public function shouldDistinguishBooleanFromIntWithStrictComparison(): void
    {
        $metric = new Equals();

        // true vs 1 - strict comparison should fail
        $result = $metric->score([
            'output' => true,
            'expected' => 1,
        ]);

        self::assertSame(0.0, $result->value);
    }

    #[Test]
    public function shouldMatchBooleanAndIntWithLooseComparison(): void
    {
        $metric = new Equals(strict: false);

        // true vs 1 - loose comparison should pass
        $result = $metric->score([
            'output' => true,
            'expected' => 1,
        ]);

        self::assertSame(1.0, $result->value);
    }

    #[Test]
    public function shouldHandleMissingKeys(): void
    {
        $metric = new Equals();

        $result = $metric->score([]);

        // Both default to null, so they match
        self::assertSame(1.0, $result->value);
    }

    #[Test]
    public function shouldHandleFloatComparison(): void
    {
        $metric = new Equals();

        $result = $metric->score([
            'output' => 3.14,
            'expected' => 3.14,
        ]);

        self::assertSame(1.0, $result->value);
    }

    #[Test]
    public function shouldIndicateComparisonTypeInReason(): void
    {
        $strictMetric = new Equals(strict: true);
        $looseMetric = new Equals(strict: false);

        $strictResult = $strictMetric->score([
            'output' => 'test',
            'expected' => 'test',
        ]);

        $looseResult = $looseMetric->score([
            'output' => 'test',
            'expected' => 'test',
        ]);

        self::assertStringContainsString('strict', $strictResult->reason);
        self::assertStringContainsString('loose', $looseResult->reason);
    }
}
