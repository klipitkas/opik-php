<?php

declare(strict_types=1);

namespace Opik\Tests\Unit\Evaluation\Metrics;

use Opik\Evaluation\Metrics\Contains;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContainsTest extends TestCase
{
    #[Test]
    public function shouldReturnOneWhenOutputContainsExpected(): void
    {
        $metric = new Contains();

        $result = $metric->score([
            'output' => 'hello world',
            'expected' => 'world',
        ]);

        self::assertSame('contains', $result->name);
        self::assertSame(1.0, $result->value);
        self::assertStringContainsString('contains the expected', $result->reason);
    }

    #[Test]
    public function shouldReturnZeroWhenOutputDoesNotContainExpected(): void
    {
        $metric = new Contains();

        $result = $metric->score([
            'output' => 'hello world',
            'expected' => 'goodbye',
        ]);

        self::assertSame(0.0, $result->value);
        self::assertStringContainsString('does not contain', $result->reason);
    }

    #[Test]
    public function shouldBeCaseSensitiveByDefault(): void
    {
        $metric = new Contains();

        $result = $metric->score([
            'output' => 'Hello World',
            'expected' => 'hello',
        ]);

        self::assertSame(0.0, $result->value);
    }

    #[Test]
    public function shouldSupportCaseInsensitive(): void
    {
        $metric = new Contains(caseSensitive: false);

        $result = $metric->score([
            'output' => 'Hello World',
            'expected' => 'hello',
        ]);

        self::assertSame(1.0, $result->value);
    }

    #[Test]
    public function shouldReturnOneForExactMatch(): void
    {
        $metric = new Contains();

        $result = $metric->score([
            'output' => 'hello',
            'expected' => 'hello',
        ]);

        self::assertSame(1.0, $result->value);
    }

    #[Test]
    public function shouldUseCustomName(): void
    {
        $metric = new Contains('my_contains_metric');

        $result = $metric->score([
            'output' => 'test',
            'expected' => 'test',
        ]);

        self::assertSame('my_contains_metric', $result->name);
    }

    #[Test]
    public function shouldHandleEmptyStrings(): void
    {
        $metric = new Contains();

        // Empty string is contained in any string
        $result = $metric->score([
            'output' => 'hello',
            'expected' => '',
        ]);

        self::assertSame(1.0, $result->value);
    }
}
