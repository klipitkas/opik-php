<?php

declare(strict_types=1);

namespace Opik\Tests\Unit\Evaluation\Metrics;

use Opik\Evaluation\Metrics\ExactMatch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExactMatchTest extends TestCase
{
    #[Test]
    #[DataProvider('scoreCases')]
    public function shouldCalculateScore(mixed $output, mixed $expected, float $expectedScore): void
    {
        $result = (new ExactMatch())->score(['output' => $output, 'expected' => $expected]);

        self::assertSame($expectedScore, $result->value);
    }

    /** @return iterable<string, array{mixed, mixed, float}> */
    public static function scoreCases(): iterable
    {
        yield 'exact string match' => ['hello world', 'hello world', 1.0];
        yield 'no match' => ['hello world', 'goodbye world', 0.0];
        yield 'case sensitive' => ['Hello World', 'hello world', 0.0];
        yield 'arrays match' => [['a' => 1, 'b' => 2], ['a' => 1, 'b' => 2], 1.0];
        yield 'arrays differ' => [['a' => 1], ['a' => 2], 0.0];
        yield 'null values' => [null, null, 1.0];
    }

    #[Test]
    public function shouldUseDefaultName(): void
    {
        $result = (new ExactMatch())->score(['output' => 'test', 'expected' => 'test']);

        self::assertSame('exact_match', $result->name);
        self::assertSame('Exact match: Match', $result->reason);
    }

    #[Test]
    public function shouldUseCustomName(): void
    {
        $result = (new ExactMatch('custom'))->score(['output' => 'test', 'expected' => 'test']);

        self::assertSame('custom', $result->name);
    }

    #[Test]
    public function shouldHandleMissingKeys(): void
    {
        $result = (new ExactMatch())->score([]);

        self::assertSame(1.0, $result->value);
    }
}
