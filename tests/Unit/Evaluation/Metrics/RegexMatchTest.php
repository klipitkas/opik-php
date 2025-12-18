<?php

declare(strict_types=1);

namespace Opik\Tests\Unit\Evaluation\Metrics;

use Opik\Evaluation\Metrics\RegexMatch;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RegexMatchTest extends TestCase
{
    #[Test]
    public function shouldReturnOneForMatch(): void
    {
        $metric = new RegexMatch();

        $result = $metric->score([
            'output' => 'hello world',
            'pattern' => '/world/',
        ]);

        self::assertSame('regex_match', $result->name);
        self::assertSame(1.0, $result->value);
        self::assertStringContainsString('matches', $result->reason);
    }

    #[Test]
    public function shouldReturnZeroForNoMatch(): void
    {
        $metric = new RegexMatch();

        $result = $metric->score([
            'output' => 'hello world',
            'pattern' => '/goodbye/',
        ]);

        self::assertSame(0.0, $result->value);
        self::assertStringContainsString('does not match', $result->reason);
    }

    #[Test]
    public function shouldAutoAddDelimiters(): void
    {
        $metric = new RegexMatch();

        $result = $metric->score([
            'output' => 'hello world',
            'pattern' => 'world',
        ]);

        self::assertSame(1.0, $result->value);
    }

    #[Test]
    public function shouldSupportRegexFlags(): void
    {
        $metric = new RegexMatch();

        // Case insensitive match
        $result = $metric->score([
            'output' => 'Hello World',
            'pattern' => '/hello/i',
        ]);

        self::assertSame(1.0, $result->value);
    }

    #[Test]
    public function shouldMatchEmailPattern(): void
    {
        $metric = new RegexMatch();

        $result = $metric->score([
            'output' => 'Contact me at test@example.com',
            'pattern' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
        ]);

        self::assertSame(1.0, $result->value);
    }

    #[Test]
    public function shouldUseCustomName(): void
    {
        $metric = new RegexMatch('email_validator');

        $result = $metric->score([
            'output' => 'test@example.com',
            'pattern' => '/@/',
        ]);

        self::assertSame('email_validator', $result->name);
    }

    #[Test]
    public function shouldHandleInvalidPattern(): void
    {
        $metric = new RegexMatch();

        // Invalid regex pattern - should not match
        $result = $metric->score([
            'output' => 'test',
            'pattern' => '/[invalid/',
        ]);

        self::assertSame(0.0, $result->value);
    }

    #[Test]
    public function shouldSupportHashDelimiter(): void
    {
        $metric = new RegexMatch();

        $result = $metric->score([
            'output' => 'http://example.com',
            'pattern' => '#http://#',
        ]);

        self::assertSame(1.0, $result->value);
    }
}
