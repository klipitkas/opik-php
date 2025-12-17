<?php

declare(strict_types=1);

namespace Opik\Tests\Integration;

use Opik\Feedback\FeedbackScore;
use Opik\OpikClient;
use Opik\Tracer\SpanType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Integration tests for trace operations against Opik Cloud.
 *
 * These tests require the following environment variables:
 * - OPIK_API_KEY: API key for authentication
 * - OPIK_WORKSPACE: Workspace name
 * - OPIK_PROJECT_NAME: Project name for test traces
 */
final class TraceIntegrationTest extends TestCase
{
    private ?OpikClient $client = null;

    private string $projectName;

    protected function setUp(): void
    {
        $apiKey = getenv('OPIK_API_KEY');
        $workspace = getenv('OPIK_WORKSPACE');

        if ($apiKey === false || $apiKey === '') {
            self::markTestSkipped('OPIK_API_KEY environment variable not set');
        }

        if ($workspace === false || $workspace === '') {
            self::markTestSkipped('OPIK_WORKSPACE environment variable not set');
        }

        // Use a unique project name for each test to ensure isolation
        $this->projectName = 'php-sdk-test-' . uniqid();

        $this->client = new OpikClient(
            apiKey: $apiKey,
            workspace: $workspace,
            projectName: $this->projectName,
        );
    }

    protected function tearDown(): void
    {
        // Cleanup: delete the entire test project
        if ($this->client !== null) {
            try {
                $project = $this->client->getProjectByName($this->projectName);
                if (isset($project['id'])) {
                    $this->client->deleteProject($project['id']);
                }
            } catch (Throwable) {
                // Ignore cleanup errors (project may not exist)
            }
        }
    }

    #[Test]
    public function shouldCreateAndRetrieveTrace(): void
    {
        self::assertNotNull($this->client);

        // Create a trace
        $trace = $this->client->trace(
            name: 'integration-test-trace',
            input: ['query' => 'What is PHP?'],
            metadata: ['test' => true, 'environment' => 'integration'],
            tags: ['integration-test', 'php-sdk'],
        );

        $traceId = $trace->getId();

        // Update trace with output
        $trace->update(output: ['response' => 'PHP is a programming language.']);
        $trace->end();

        // Flush to ensure data is sent
        $this->client->flush();

        // Wait a bit for the backend to process
        usleep(500000); // 500ms

        // Retrieve and verify
        $retrievedTrace = $this->client->getTraceContent($traceId);

        self::assertSame($traceId, $retrievedTrace['id']);
        self::assertSame('integration-test-trace', $retrievedTrace['name']);
        self::assertArrayHasKey('project_id', $retrievedTrace);

        // Verify input values
        self::assertSame(['query' => 'What is PHP?'], $retrievedTrace['input']);

        // Verify output values
        self::assertSame(['response' => 'PHP is a programming language.'], $retrievedTrace['output']);
    }

    #[Test]
    public function shouldCreateTraceWithSpans(): void
    {
        self::assertNotNull($this->client);

        // Create a trace
        $trace = $this->client->trace(
            name: 'integration-test-trace-with-spans',
            input: ['question' => 'Explain integration testing'],
        );

        $traceId = $trace->getId();

        // Create a general span
        $span1 = $trace->span(
            name: 'preprocessing',
            type: SpanType::GENERAL,
            input: ['raw_question' => 'Explain integration testing'],
        );
        $span1->update(output: ['processed' => true]);
        $span1->end();

        // Create an LLM span
        $llmSpan = $trace->span(
            name: 'llm-call',
            type: SpanType::LLM,
            input: ['prompt' => 'Explain integration testing'],
            metadata: ['model' => 'gpt-4', 'temperature' => 0.7],
        );
        $llmSpan->update(
            output: ['response' => 'Integration testing verifies that different modules work together.'],
        );
        $llmSpan->end();

        $trace->update(output: ['final_response' => 'Integration testing verifies modules work together.']);
        $trace->end();

        // Flush and wait
        $this->client->flush();
        usleep(500000);

        // Verify trace
        $retrievedTrace = $this->client->getTraceContent($traceId);
        self::assertSame('integration-test-trace-with-spans', $retrievedTrace['name']);
        self::assertSame(['question' => 'Explain integration testing'], $retrievedTrace['input']);
        self::assertSame(['final_response' => 'Integration testing verifies modules work together.'], $retrievedTrace['output']);

        // Verify spans
        $spans = $this->client->searchSpans(traceId: $traceId);
        self::assertArrayHasKey('content', $spans);
        self::assertCount(2, $spans['content']);

        // Build spans by name for easier verification
        $spansByName = [];
        foreach ($spans['content'] as $span) {
            $spansByName[$span['name']] = $span;
        }

        // Verify preprocessing span
        self::assertSame('general', $spansByName['preprocessing']['type']);
        self::assertSame(['raw_question' => 'Explain integration testing'], $spansByName['preprocessing']['input']);
        self::assertSame(['processed' => true], $spansByName['preprocessing']['output']);

        // Verify LLM span
        self::assertSame('llm', $spansByName['llm-call']['type']);
        self::assertSame(['prompt' => 'Explain integration testing'], $spansByName['llm-call']['input']);
        self::assertSame(['response' => 'Integration testing verifies that different modules work together.'], $spansByName['llm-call']['output']);
    }

    #[Test]
    public function shouldSearchTracesByProjectName(): void
    {
        self::assertNotNull($this->client);

        // Create a trace with a unique name
        $uniqueName = 'search-test-' . uniqid();
        $trace = $this->client->trace(
            name: $uniqueName,
            input: ['test' => 'search'],
        );

        $traceId = $trace->getId();
        $trace->end();

        $this->client->flush();
        usleep(500000);

        // Search for traces in the project
        $results = $this->client->searchTraces(projectName: $this->projectName);

        self::assertArrayHasKey('content', $results);
        self::assertNotEmpty($results['content']);

        // Verify our trace is in the results
        $traceIds = array_column($results['content'], 'id');
        self::assertContains($traceId, $traceIds);
    }

    #[Test]
    public function shouldAddFeedbackScoreToTrace(): void
    {
        self::assertNotNull($this->client);

        // Create a trace
        $trace = $this->client->trace(
            name: 'feedback-test-trace',
            input: ['query' => 'Test feedback'],
        );

        $traceId = $trace->getId();
        $trace->end();

        $this->client->flush();
        usleep(500000);

        // Add feedback score
        $this->client->logTracesFeedbackScores([
            FeedbackScore::forTrace(
                traceId: $traceId,
                name: 'accuracy',
                value: 0.95,
                reason: 'Test feedback score',
            ),
        ]);

        usleep(500000);

        // Retrieve trace and verify feedback
        $retrievedTrace = $this->client->getTraceContent($traceId);
        self::assertArrayHasKey('feedback_scores', $retrievedTrace);
        self::assertCount(1, $retrievedTrace['feedback_scores']);

        $feedbackScore = $retrievedTrace['feedback_scores'][0];
        self::assertSame('accuracy', $feedbackScore['name']);
        self::assertSame(0.95, $feedbackScore['value']);
        self::assertSame('Test feedback score', $feedbackScore['reason']);
    }

    #[Test]
    public function shouldDeleteTrace(): void
    {
        self::assertNotNull($this->client);

        // Create a trace
        $trace = $this->client->trace(
            name: 'delete-test-trace',
            input: ['test' => 'delete'],
        );

        $traceId = $trace->getId();
        $trace->end();

        $this->client->flush();
        usleep(500000);

        // Verify it exists
        $retrievedTrace = $this->client->getTraceContent($traceId);
        self::assertSame($traceId, $retrievedTrace['id']);

        // Delete the trace
        $this->client->deleteTraces([$traceId]);
        usleep(500000);

        // Verify it's deleted (should throw or return empty)
        $this->expectException(Throwable::class);
        $this->client->getTraceContent($traceId);
    }

    #[Test]
    public function shouldCreateTraceWithThread(): void
    {
        self::assertNotNull($this->client);

        $threadId = 'test-thread-' . uniqid();

        // Create first trace in thread
        $trace1 = $this->client->trace(
            name: 'thread-trace-1',
            input: ['message' => 'Hello'],
            threadId: $threadId,
        );
        $trace1Id = $trace1->getId();
        $trace1->end();

        // Create second trace in same thread
        $trace2 = $this->client->trace(
            name: 'thread-trace-2',
            input: ['message' => 'How are you?'],
            threadId: $threadId,
        );
        $trace2Id = $trace2->getId();
        $trace2->end();

        $this->client->flush();
        usleep(500000);

        // Verify both traces have correct values
        $retrievedTrace1 = $this->client->getTraceContent($trace1Id);
        $retrievedTrace2 = $this->client->getTraceContent($trace2Id);

        // Verify first trace
        self::assertSame('thread-trace-1', $retrievedTrace1['name']);
        self::assertSame(['message' => 'Hello'], $retrievedTrace1['input']);
        self::assertSame($threadId, $retrievedTrace1['thread_id'] ?? null);

        // Verify second trace
        self::assertSame('thread-trace-2', $retrievedTrace2['name']);
        self::assertSame(['message' => 'How are you?'], $retrievedTrace2['input']);
        self::assertSame($threadId, $retrievedTrace2['thread_id'] ?? null);
    }

    #[Test]
    public function shouldCreateNestedSpans(): void
    {
        self::assertNotNull($this->client);

        // Create a trace
        $trace = $this->client->trace(
            name: 'nested-spans-test',
            input: ['query' => 'Test nested spans'],
        );

        $traceId = $trace->getId();

        // Create parent span
        $parentSpan = $trace->span(
            name: 'parent-span',
            type: SpanType::GENERAL,
            input: ['step' => 'parent'],
        );
        $parentSpanId = $parentSpan->getId();

        // Create child span within parent
        $childSpan = $parentSpan->span(
            name: 'child-span',
            type: SpanType::LLM,
            input: ['step' => 'child'],
        );
        $childSpanId = $childSpan->getId();

        // Create grandchild span
        $grandchildSpan = $childSpan->span(
            name: 'grandchild-span',
            type: SpanType::GENERAL,
            input: ['step' => 'grandchild'],
        );
        $grandchildSpan->update(output: ['result' => 'done']);
        $grandchildSpan->end();

        $childSpan->update(output: ['result' => 'child done']);
        $childSpan->end();

        $parentSpan->update(output: ['result' => 'parent done']);
        $parentSpan->end();

        $trace->end();

        $this->client->flush();
        usleep(500000);

        // Verify spans and their hierarchy
        $spans = $this->client->searchSpans(traceId: $traceId);
        self::assertArrayHasKey('content', $spans);
        self::assertCount(3, $spans['content']);

        // Find each span and verify parent relationships
        $spansByName = [];
        foreach ($spans['content'] as $span) {
            $spansByName[$span['name']] = $span;
        }

        // Verify parent span
        self::assertArrayNotHasKey('parent_span_id', $spansByName['parent-span']);
        self::assertSame('general', $spansByName['parent-span']['type']);
        self::assertSame(['step' => 'parent'], $spansByName['parent-span']['input']);
        self::assertSame(['result' => 'parent done'], $spansByName['parent-span']['output']);

        // Verify child span
        self::assertSame($parentSpanId, $spansByName['child-span']['parent_span_id']);
        self::assertSame('llm', $spansByName['child-span']['type']);
        self::assertSame(['step' => 'child'], $spansByName['child-span']['input']);
        self::assertSame(['result' => 'child done'], $spansByName['child-span']['output']);

        // Verify grandchild span
        self::assertSame($childSpanId, $spansByName['grandchild-span']['parent_span_id']);
        self::assertSame('general', $spansByName['grandchild-span']['type']);
        self::assertSame(['step' => 'grandchild'], $spansByName['grandchild-span']['input']);
        self::assertSame(['result' => 'done'], $spansByName['grandchild-span']['output']);
    }

    #[Test]
    public function shouldAddFeedbackScoreToSpan(): void
    {
        self::assertNotNull($this->client);

        // Create a trace with a span
        $trace = $this->client->trace(
            name: 'span-feedback-test',
            input: ['query' => 'Test span feedback'],
        );

        $span = $trace->span(
            name: 'span-for-feedback',
            type: SpanType::LLM,
            input: ['prompt' => 'Test prompt'],
        );
        $spanId = $span->getId();
        $span->update(output: ['response' => 'Test response']);
        $span->end();

        $trace->end();

        $this->client->flush();
        usleep(500000);

        // Add feedback score to span
        $this->client->logSpansFeedbackScores([
            FeedbackScore::forSpan(
                spanId: $spanId,
                name: 'relevance',
                value: 0.85,
                reason: 'Test span feedback score',
            ),
        ]);

        usleep(500000);

        // Retrieve span and verify feedback
        $spanContent = $this->client->getSpanContent($spanId);
        self::assertArrayHasKey('feedback_scores', $spanContent);
        self::assertCount(1, $spanContent['feedback_scores']);

        $feedbackScore = $spanContent['feedback_scores'][0];
        self::assertSame('relevance', $feedbackScore['name']);
        self::assertSame(0.85, $feedbackScore['value']);
        self::assertSame('Test span feedback score', $feedbackScore['reason']);
    }
}
