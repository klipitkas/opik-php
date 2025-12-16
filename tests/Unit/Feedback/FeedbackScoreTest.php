<?php

declare(strict_types=1);

namespace Opik\Tests\Unit\Feedback;

use InvalidArgumentException;
use Opik\Feedback\FeedbackScore;
use Opik\Feedback\FeedbackScoreSource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FeedbackScoreTest extends TestCase
{
    #[Test]
    public function shouldCreateFeedbackScoreWithNumericValue(): void
    {
        $score = new FeedbackScore(
            name: 'quality',
            value: 0.95,
            traceId: 'trace-123',
        );

        self::assertSame('quality', $score->name);
        self::assertSame(0.95, $score->value);
        self::assertSame('trace-123', $score->traceId);
        self::assertNull($score->categoryName);
        self::assertSame(FeedbackScoreSource::SDK, $score->source);
    }

    #[Test]
    public function shouldCreateFeedbackScoreWithCategoricalValue(): void
    {
        $score = new FeedbackScore(
            name: 'sentiment',
            categoryName: 'positive',
            spanId: 'span-456',
        );

        self::assertSame('sentiment', $score->name);
        self::assertNull($score->value);
        self::assertSame('positive', $score->categoryName);
        self::assertSame('span-456', $score->spanId);
    }

    #[Test]
    public function shouldThrowOnEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Feedback score name cannot be empty');

        new FeedbackScore(name: '', value: 0.5);
    }

    #[Test]
    public function shouldThrowOnWhitespaceName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Feedback score name cannot be empty');

        new FeedbackScore(name: '   ', value: 0.5);
    }

    #[Test]
    public function shouldThrowWhenNeitherValueNorCategoryProvided(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Either value or categoryName must be provided');

        new FeedbackScore(name: 'quality');
    }

    #[Test]
    public function shouldCreateForTrace(): void
    {
        $score = FeedbackScore::forTrace(
            traceId: 'trace-123',
            name: 'accuracy',
            value: 0.9,
            reason: 'Good response',
        );

        self::assertSame('trace-123', $score->traceId);
        self::assertSame('accuracy', $score->name);
        self::assertSame(0.9, $score->value);
        self::assertSame('Good response', $score->reason);
        self::assertNull($score->spanId);
        self::assertNull($score->threadId);
    }

    #[Test]
    public function shouldCreateForSpan(): void
    {
        $score = FeedbackScore::forSpan(
            spanId: 'span-456',
            name: 'relevance',
            categoryName: 'high',
        );

        self::assertSame('span-456', $score->spanId);
        self::assertSame('relevance', $score->name);
        self::assertSame('high', $score->categoryName);
        self::assertNull($score->traceId);
        self::assertNull($score->threadId);
    }

    #[Test]
    public function shouldCreateForThread(): void
    {
        $score = FeedbackScore::forThread(
            threadId: 'thread-789',
            name: 'satisfaction',
            value: 0.85,
        );

        self::assertSame('thread-789', $score->threadId);
        self::assertSame('satisfaction', $score->name);
        self::assertSame(0.85, $score->value);
        self::assertNull($score->traceId);
        self::assertNull($score->spanId);
    }

    #[Test]
    public function shouldConvertToArrayForTrace(): void
    {
        $score = FeedbackScore::forTrace(
            traceId: 'trace-123',
            name: 'quality',
            value: 0.9,
            reason: 'Excellent',
        );

        $array = $score->toArray('my-project');

        self::assertSame('trace-123', $array['id']);
        self::assertSame('quality', $array['name']);
        self::assertSame(0.9, $array['value']);
        self::assertSame('Excellent', $array['reason']);
        self::assertSame('sdk', $array['source']);
        self::assertSame('my-project', $array['project_name']);
    }

    #[Test]
    public function shouldConvertToArrayForSpan(): void
    {
        $score = FeedbackScore::forSpan(
            spanId: 'span-456',
            name: 'accuracy',
            categoryName: 'high',
        );

        $array = $score->toArray('my-project');

        self::assertSame('span-456', $array['id']);
        self::assertSame('accuracy', $array['name']);
        self::assertSame('high', $array['category_name']);
        self::assertSame('sdk', $array['source']);
        self::assertArrayNotHasKey('value', $array);
        self::assertArrayNotHasKey('reason', $array);
    }

    #[Test]
    public function shouldConvertToThreadArray(): void
    {
        $score = FeedbackScore::forThread(
            threadId: 'thread-789',
            name: 'helpfulness',
            value: 0.95,
            reason: 'Very helpful',
        );

        $array = $score->toThreadArray('my-project');

        self::assertSame('thread-789', $array['thread_id']);
        self::assertSame('helpfulness', $array['name']);
        self::assertSame(0.95, $array['value']);
        self::assertSame('Very helpful', $array['reason']);
        self::assertSame('sdk', $array['source']);
        self::assertSame('my-project', $array['project_name']);
        self::assertArrayNotHasKey('id', $array);
    }

    #[Test]
    public function shouldCreateFromArray(): void
    {
        $data = [
            'id' => 'score-123',
            'name' => 'quality',
            'value' => 0.8,
            'category_name' => null,
            'reason' => 'Good',
            'trace_id' => 'trace-456',
            'source' => 'ui',
        ];

        $score = FeedbackScore::fromArray($data);

        self::assertSame('score-123', $score->id);
        self::assertSame('quality', $score->name);
        self::assertSame(0.8, $score->value);
        self::assertSame('Good', $score->reason);
        self::assertSame('trace-456', $score->traceId);
        self::assertSame(FeedbackScoreSource::UI, $score->source);
    }

    #[Test]
    public function shouldUseCustomId(): void
    {
        $score = new FeedbackScore(
            name: 'quality',
            value: 0.9,
            id: 'custom-id-123',
        );

        self::assertSame('custom-id-123', $score->id);
    }

    #[Test]
    public function shouldGenerateIdWhenNotProvided(): void
    {
        $score = new FeedbackScore(
            name: 'quality',
            value: 0.9,
        );

        self::assertNotEmpty($score->id);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $score->id);
    }

    #[Test]
    public function shouldSupportAllSourceTypes(): void
    {
        $sdkScore = new FeedbackScore(name: 'test', value: 0.5, source: FeedbackScoreSource::SDK);
        $uiScore = new FeedbackScore(name: 'test', value: 0.5, source: FeedbackScoreSource::UI);
        $autoScore = new FeedbackScore(name: 'test', value: 0.5, source: FeedbackScoreSource::AUTOMATION);

        self::assertSame('sdk', $sdkScore->source->value);
        self::assertSame('ui', $uiScore->source->value);
        self::assertSame('automation', $autoScore->source->value);
    }
}
