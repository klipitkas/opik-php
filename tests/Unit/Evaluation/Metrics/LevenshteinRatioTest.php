<?php

declare(strict_types=1);

namespace Opik\Tests\Unit\Evaluation\Metrics;

use Opik\Evaluation\Metrics\LevenshteinRatio;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LevenshteinRatioTest extends TestCase
{
    #[Test]
    #[DataProvider('exactScoreCases')]
    public function shouldCalculateExactScore(mixed $output, mixed $expected, float $expectedScore): void
    {
        $result = (new LevenshteinRatio())->score(['output' => $output, 'expected' => $expected]);

        self::assertSame($expectedScore, $result->value);
    }

    /** @return iterable<string, array{mixed, mixed, float}> */
    public static function exactScoreCases(): iterable
    {
        yield 'identical strings' => ['hello world', 'hello world', 1.0];
        yield 'completely different' => ['abc', 'xyz', 0.0];
        yield 'empty strings' => ['', '', 1.0];
        yield 'one empty string' => ['hello', '', 0.0];
        yield 'number conversion' => [123, '123', 1.0];
    }

    #[Test]
    #[DataProvider('approximateScoreCases')]
    public function shouldCalculateApproximateScore(string $output, string $expected, float $expectedScore): void
    {
        $result = (new LevenshteinRatio())->score(['output' => $output, 'expected' => $expected]);

        self::assertEqualsWithDelta($expectedScore, $result->value, 0.001);
    }

    /** @return iterable<string, array{string, string, float}> */
    public static function approximateScoreCases(): iterable
    {
        yield 'one char diff' => ['hello', 'hallo', 0.8];
        yield 'case sensitive' => ['Hello', 'hello', 0.8];
        yield 'partial match' => ['cat', 'category', 0.375];
    }

    #[Test]
    public function shouldIncludeDistanceInReason(): void
    {
        $result = (new LevenshteinRatio())->score(['output' => 'hello', 'expected' => 'hallo']);

        self::assertStringContainsString('distance: 1', $result->reason);
        self::assertStringContainsString('max length: 5', $result->reason);
    }

    #[Test]
    public function shouldUseDefaultName(): void
    {
        $result = (new LevenshteinRatio())->score(['output' => 'test', 'expected' => 'test']);

        self::assertSame('levenshtein_ratio', $result->name);
    }

    #[Test]
    public function shouldUseCustomName(): void
    {
        $result = (new LevenshteinRatio('custom'))->score(['output' => 'test', 'expected' => 'test']);

        self::assertSame('custom', $result->name);
    }

    #[Test]
    public function shouldHandleMissingKeys(): void
    {
        $result = (new LevenshteinRatio())->score([]);

        self::assertSame(1.0, $result->value);
    }
}
