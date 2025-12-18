<?php

declare(strict_types=1);

namespace Opik\Tests\Unit\Evaluation\Metrics;

use Opik\Evaluation\Metrics\LevenshteinRatio;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LevenshteinRatioTest extends TestCase
{
    #[Test]
    public function shouldReturnOneForIdenticalStrings(): void
    {
        $metric = new LevenshteinRatio();

        $result = $metric->score([
            'output' => 'hello world',
            'expected' => 'hello world',
        ]);

        self::assertSame('levenshtein_ratio', $result->name);
        self::assertSame(1.0, $result->value);
    }

    #[Test]
    public function shouldReturnZeroForCompletelyDifferentStrings(): void
    {
        $metric = new LevenshteinRatio();

        $result = $metric->score([
            'output' => 'abc',
            'expected' => 'xyz',
        ]);

        self::assertSame(0.0, $result->value);
    }

    #[Test]
    public function shouldReturnPartialMatchForSimilarStrings(): void
    {
        $metric = new LevenshteinRatio();

        // "hello" vs "hallo" - one character difference
        $result = $metric->score([
            'output' => 'hello',
            'expected' => 'hallo',
        ]);

        // Distance is 1, max length is 5, ratio = 1 - (1/5) = 0.8
        self::assertEqualsWithDelta(0.8, $result->value, 0.001);
    }

    #[Test]
    public function shouldHandleEmptyStrings(): void
    {
        $metric = new LevenshteinRatio();

        $result = $metric->score([
            'output' => '',
            'expected' => '',
        ]);

        self::assertSame(1.0, $result->value);
        self::assertStringContainsString('empty', $result->reason);
    }

    #[Test]
    public function shouldHandleOneEmptyString(): void
    {
        $metric = new LevenshteinRatio();

        $result = $metric->score([
            'output' => 'hello',
            'expected' => '',
        ]);

        // Distance is 5, max length is 5, ratio = 1 - (5/5) = 0.0
        self::assertSame(0.0, $result->value);
    }

    #[Test]
    public function shouldUseCustomName(): void
    {
        $metric = new LevenshteinRatio('text_similarity');

        $result = $metric->score([
            'output' => 'test',
            'expected' => 'test',
        ]);

        self::assertSame('text_similarity', $result->name);
    }

    #[Test]
    public function shouldHandleDifferentLengthStrings(): void
    {
        $metric = new LevenshteinRatio();

        $result = $metric->score([
            'output' => 'cat',
            'expected' => 'category',
        ]);

        // Distance is 5 (add "egory"), max length is 8
        // ratio = 1 - (5/8) = 0.375
        self::assertEqualsWithDelta(0.375, $result->value, 0.001);
    }

    #[Test]
    public function shouldConvertNonStringsToStrings(): void
    {
        $metric = new LevenshteinRatio();

        $result = $metric->score([
            'output' => 123,
            'expected' => '123',
        ]);

        self::assertSame(1.0, $result->value);
    }

    #[Test]
    public function shouldHandleMissingKeys(): void
    {
        $metric = new LevenshteinRatio();

        $result = $metric->score([]);

        // Both default to empty string, so they match
        self::assertSame(1.0, $result->value);
    }

    #[Test]
    public function shouldIncludeDistanceInReason(): void
    {
        $metric = new LevenshteinRatio();

        $result = $metric->score([
            'output' => 'hello',
            'expected' => 'hallo',
        ]);

        self::assertStringContainsString('distance: 1', $result->reason);
        self::assertStringContainsString('max length: 5', $result->reason);
    }

    #[Test]
    public function shouldBeCaseSensitive(): void
    {
        $metric = new LevenshteinRatio();

        $result = $metric->score([
            'output' => 'Hello',
            'expected' => 'hello',
        ]);

        // One character different
        self::assertEqualsWithDelta(0.8, $result->value, 0.001);
    }
}
