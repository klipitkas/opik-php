<?php

declare(strict_types=1);

namespace Opik\Tests\Integration;

use Opik\OpikClient;
use Opik\Prompt\ChatMessage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Integration tests for prompt operations against Opik Cloud.
 *
 * These tests require the following environment variables:
 * - OPIK_API_KEY: API key for authentication
 * - OPIK_WORKSPACE: Workspace name
 */
final class PromptIntegrationTest extends TestCase
{
    private ?OpikClient $client = null;

    private string $promptName;

    /** @var array<int, string> */
    private array $createdPromptIds = [];

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

        $this->promptName = 'php-sdk-test-prompt-' . uniqid();

        $this->client = new OpikClient(
            apiKey: $apiKey,
            workspace: $workspace,
        );
    }

    protected function tearDown(): void
    {
        if ($this->client !== null && $this->createdPromptIds !== []) {
            try {
                $this->client->deletePrompts($this->createdPromptIds);
            } catch (Throwable) {
                // Ignore cleanup errors
            }
        }
    }

    #[Test]
    public function shouldCreateAndRetrieveTextPrompt(): void
    {
        self::assertNotNull($this->client);

        $prompt = $this->client->createPrompt(
            name: $this->promptName,
            template: 'Hello, {{name}}! Welcome to {{place}}.',
        );
        $this->createdPromptIds[] = $prompt->id;

        self::assertSame($this->promptName, $prompt->name);
        self::assertNotEmpty($prompt->id);

        // Retrieve and verify
        $retrieved = $this->client->getPrompt($this->promptName);
        self::assertSame($prompt->id, $retrieved->id);
        self::assertSame($this->promptName, $retrieved->name);
    }

    #[Test]
    public function shouldFormatTextPromptWithVariables(): void
    {
        self::assertNotNull($this->client);

        $prompt = $this->client->createPrompt(
            name: $this->promptName,
            template: 'Hello, {{name}}! You are learning {{language}}.',
        );
        $this->createdPromptIds[] = $prompt->id;

        $formatted = $prompt->format([
            'name' => 'Developer',
            'language' => 'PHP',
        ]);

        self::assertSame('Hello, Developer! You are learning PHP.', $formatted);
    }

    #[Test]
    public function shouldCreateAndRetrieveChatPrompt(): void
    {
        self::assertNotNull($this->client);

        $prompt = $this->client->createPrompt(
            name: $this->promptName,
            template: [
                ChatMessage::system('You are a helpful assistant.'),
                ChatMessage::user('Tell me about {{topic}}.'),
            ],
        );
        $this->createdPromptIds[] = $prompt->id;

        self::assertSame($this->promptName, $prompt->name);

        $version = $prompt->getLatestVersion();
        self::assertTrue($version->isChat());
    }

    #[Test]
    public function shouldFormatChatPromptWithVariables(): void
    {
        self::assertNotNull($this->client);

        $prompt = $this->client->createPrompt(
            name: $this->promptName,
            template: [
                ChatMessage::system('You are a {{role}}.'),
                ChatMessage::user('Help me with {{task}}.'),
            ],
        );
        $this->createdPromptIds[] = $prompt->id;

        $formatted = $prompt->format([
            'role' => 'coding assistant',
            'task' => 'PHP development',
        ]);

        self::assertIsArray($formatted);
        self::assertCount(2, $formatted);
        self::assertSame('system', $formatted[0]['role']);
        self::assertSame('You are a coding assistant.', $formatted[0]['content']);
        self::assertSame('user', $formatted[1]['role']);
        self::assertSame('Help me with PHP development.', $formatted[1]['content']);
    }

    #[Test]
    public function shouldListPrompts(): void
    {
        self::assertNotNull($this->client);

        $prompt = $this->client->createPrompt(
            name: $this->promptName,
            template: 'Test prompt template',
        );
        $this->createdPromptIds[] = $prompt->id;

        // Returns array of Prompt objects
        $prompts = $this->client->getPrompts();

        self::assertIsArray($prompts);

        // Our prompt should be in the list
        $found = false;
        foreach ($prompts as $p) {
            if ($p->name === $this->promptName) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Created prompt should be in the list');
    }

    #[Test]
    public function shouldSearchPromptsByName(): void
    {
        self::assertNotNull($this->client);

        $prompt = $this->client->createPrompt(
            name: $this->promptName,
            template: 'Searchable prompt',
        );
        $this->createdPromptIds[] = $prompt->id;

        // Returns array of Prompt objects
        $prompts = $this->client->searchPrompts(name: $this->promptName);

        self::assertIsArray($prompts);
        self::assertNotEmpty($prompts);
        self::assertSame($this->promptName, $prompts[0]->name);
    }

    #[Test]
    public function shouldGetPromptHistory(): void
    {
        self::assertNotNull($this->client);

        // Create prompt
        $prompt = $this->client->createPrompt(
            name: $this->promptName,
            template: 'Test prompt: {{var}}',
        );
        $this->createdPromptIds[] = $prompt->id;

        // Get history (returns array of versions directly)
        $history = $this->client->getPromptHistory($this->promptName);

        self::assertIsArray($history);
        self::assertGreaterThanOrEqual(1, \count($history));

        // Verify the version has expected fields
        $version = $history[0];
        self::assertArrayHasKey('commit', $version);
        self::assertArrayHasKey('template', $version);
    }

    #[Test]
    public function shouldCreatePromptWithTags(): void
    {
        self::assertNotNull($this->client);

        $tags = ['production', 'v1'];

        $prompt = $this->client->createPrompt(
            name: $this->promptName,
            template: 'Hello {{name}}!',
            tags: $tags,
        );
        $this->createdPromptIds[] = $prompt->id;

        usleep(500000);

        $history = $this->client->getPromptHistory($this->promptName);

        self::assertCount(1, $history);
        self::assertArrayHasKey('tags', $history[0]);
        self::assertContains('production', $history[0]['tags']);
        self::assertContains('v1', $history[0]['tags']);
    }

    #[Test]
    public function shouldUpdatePromptVersionTags(): void
    {
        self::assertNotNull($this->client);

        $prompt = $this->client->createPrompt(
            name: $this->promptName,
            template: 'Hello {{name}}!',
            tags: ['initial'],
        );
        $this->createdPromptIds[] = $prompt->id;

        usleep(500000);

        $history = $this->client->getPromptHistory($this->promptName);
        $versionId = $history[0]['id'];

        // Replace tags
        $this->client->updatePromptVersionTags(
            versionIds: [$versionId],
            tags: ['replaced', 'new'],
            merge: false,
        );

        usleep(500000);

        $updatedHistory = $this->client->getPromptHistory($this->promptName);

        self::assertContains('replaced', $updatedHistory[0]['tags']);
        self::assertContains('new', $updatedHistory[0]['tags']);
        self::assertNotContains('initial', $updatedHistory[0]['tags']);
    }

    #[Test]
    public function shouldMergePromptVersionTags(): void
    {
        self::assertNotNull($this->client);

        $prompt = $this->client->createPrompt(
            name: $this->promptName,
            template: 'Hello {{name}}!',
            tags: ['existing'],
        );
        $this->createdPromptIds[] = $prompt->id;

        usleep(500000);

        $history = $this->client->getPromptHistory($this->promptName);
        $versionId = $history[0]['id'];

        // Merge tags
        $this->client->updatePromptVersionTags(
            versionIds: [$versionId],
            tags: ['merged'],
            merge: true,
        );

        usleep(500000);

        $updatedHistory = $this->client->getPromptHistory($this->promptName);

        self::assertContains('existing', $updatedHistory[0]['tags']);
        self::assertContains('merged', $updatedHistory[0]['tags']);
    }

    #[Test]
    public function shouldVerifyTagsInPromptHistory(): void
    {
        self::assertNotNull($this->client);

        $tags = ['staging', 'beta', 'reviewed'];

        $prompt = $this->client->createPrompt(
            name: $this->promptName,
            template: 'Test with tags: {{var}}',
            tags: $tags,
        );
        $this->createdPromptIds[] = $prompt->id;

        usleep(500000);

        $history = $this->client->getPromptHistory($this->promptName);

        self::assertCount(1, $history);
        self::assertArrayHasKey('tags', $history[0]);
        self::assertCount(3, $history[0]['tags']);
        self::assertContains('staging', $history[0]['tags']);
        self::assertContains('beta', $history[0]['tags']);
        self::assertContains('reviewed', $history[0]['tags']);
    }
}
