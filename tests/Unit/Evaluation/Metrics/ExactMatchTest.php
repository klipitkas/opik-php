<?php

declare(strict_types=1);

namespace Opik\Tests\Unit\Evaluation\Metrics;

use Opik\Evaluation\Metrics\ExactMatch;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExactMatchTest extends TestCase
{
    #[Test]
    public function shouldReturnOneForExactMatch(): void
    {
        $metric = new ExactMatch();

        $result = $metric->score([
            'output' => 'hello world',
            'expected' => 'hello world',
        ]);

        self::assertSame('exact_match', $result->name);
        self::assertSame(1.0, $result->value);
        self::assertSame('Exact match: Match', $result->reason);
    }

    #[Test]
    public function shouldReturnZeroForNoMatch(): void
    {
        $metric = new ExactMatch();

        $result = $metric->score([
            'output' => 'hello world',
            'expected' => 'goodbye world',
        ]);

        self::assertSame(0.0, $result->value);
        self::assertSame('Exact match: No match', $result->reason);
    }

    #[Test]
    public function shouldBeCaseSensitive(): void
    {
        $metric = new ExactMatch();

        $result = $metric->score([
            'output' => 'Hello World',
            'expected' => 'hello world',
        ]);

        self::assertSame(0.0, $result->value);
    }

    #[Test]
    public function shouldMatchArrays(): void
    {
        $metric = new ExactMatch();

        $result = $metric->score([
            'output' => ['a' => 1, 'b' => 2],
            'expected' => ['a' => 1, 'b' => 2],
        ]);

        self::assertSame(1.0, $result->value);
    }

    #[Test]
    public function shouldNotMatchDifferentArrays(): void
    {
        $metric = new ExactMatch();

        $result = $metric->score([
            'output' => ['a' => 1],
            'expected' => ['a' => 2],
        ]);

        self::assertSame(0.0, $result->value);
    }

    #[Test]
    public function shouldUseCustomName(): void
    {
        $metric = new ExactMatch('my_custom_metric');

        $result = $metric->score([
            'output' => 'test',
            'expected' => 'test',
        ]);

        self::assertSame('my_custom_metric', $result->name);
    }

    #[Test]
    public function shouldHandleNullValues(): void
    {
        $metric = new ExactMatch();

        $result = $metric->score([
            'output' => null,
            'expected' => null,
        ]);

        self::assertSame(1.0, $result->value);
    }

    #[Test]
    public function shouldHandleMissingKeys(): void
    {
        $metric = new ExactMatch();

        $result = $metric->score([]);

        // Both default to null, so they match
        self::assertSame(1.0, $result->value);
    }
}
