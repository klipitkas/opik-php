<?php

declare(strict_types=1);

namespace Opik\Tests\Unit\Evaluation\Metrics;

use Opik\Evaluation\Metrics\RegexMatch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RegexMatchTest extends TestCase
{
    #[Test]
    #[DataProvider('matchCases')]
    public function shouldCalculateScore(string $output, string $pattern, float $expectedScore): void
    {
        $result = (new RegexMatch())->score(['output' => $output, 'pattern' => $pattern]);

        self::assertSame($expectedScore, $result->value);
    }

    /** @return iterable<string, array{string, string, float}> */
    public static function matchCases(): iterable
    {
        yield 'simple match' => ['hello world', '/world/', 1.0];
        yield 'no match' => ['hello world', '/goodbye/', 0.0];
        yield 'auto delimiters' => ['hello world', 'world', 1.0];
        yield 'case insensitive' => ['Hello World', '/hello/i', 1.0];
        yield 'email pattern' => ['test@example.com', '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}/', 1.0];
        yield 'hash delimiter' => ['http://example.com', '#http://#', 1.0];
        yield 'invalid pattern' => ['test', '/(?invalid/', 0.0];
    }

    #[Test]
    public function shouldUseDefaultName(): void
    {
        $result = (new RegexMatch())->score(['output' => 'test', 'pattern' => '/test/']);

        self::assertSame('regex_match', $result->name);
        self::assertStringContainsString('matches', $result->reason);
    }

    #[Test]
    public function shouldUseCustomName(): void
    {
        $result = (new RegexMatch('custom'))->score(['output' => 'test', 'pattern' => '/test/']);

        self::assertSame('custom', $result->name);
    }
}
