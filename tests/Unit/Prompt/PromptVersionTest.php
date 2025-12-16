<?php

declare(strict_types=1);

namespace Opik\Tests\Unit\Prompt;

use Opik\Prompt\PromptType;
use Opik\Prompt\PromptVersion;
use Opik\Prompt\TemplateStructure;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PromptVersionTest extends TestCase
{
    #[Test]
    public function shouldCreateTextPromptVersion(): void
    {
        $version = new PromptVersion(
            id: 'version-123',
            promptId: 'prompt-456',
            commit: 'abc123',
            template: 'Hello {{name}}!',
        );

        self::assertSame('version-123', $version->id);
        self::assertSame('prompt-456', $version->promptId);
        self::assertSame('abc123', $version->commit);
        self::assertSame('Hello {{name}}!', $version->template);
        self::assertSame(PromptType::TEXT, $version->type);
        self::assertSame(TemplateStructure::TEXT, $version->templateStructure);
        self::assertTrue($version->isText());
        self::assertFalse($version->isChat());
    }

    #[Test]
    public function shouldCreateChatPromptVersion(): void
    {
        $messages = [
            ['role' => 'system', 'content' => 'You are helpful.'],
            ['role' => 'user', 'content' => '{{question}}'],
        ];

        $version = new PromptVersion(
            id: 'version-123',
            promptId: 'prompt-456',
            commit: 'abc123',
            template: $messages,
            templateStructure: TemplateStructure::CHAT,
        );

        self::assertSame($messages, $version->template);
        self::assertSame(TemplateStructure::CHAT, $version->templateStructure);
        self::assertTrue($version->isChat());
        self::assertFalse($version->isText());
    }

    #[Test]
    public function shouldFormatTextTemplate(): void
    {
        $version = new PromptVersion(
            id: 'version-123',
            promptId: 'prompt-456',
            commit: 'abc123',
            template: 'Hello {{name}}, welcome to {{place}}!',
        );

        $result = $version->format(['name' => 'John', 'place' => 'Opik']);

        self::assertSame('Hello John, welcome to Opik!', $result);
    }

    #[Test]
    public function shouldFormatTextTemplateWithSpaces(): void
    {
        $version = new PromptVersion(
            id: 'version-123',
            promptId: 'prompt-456',
            commit: 'abc123',
            template: 'Hello {{ name }}!',
        );

        $result = $version->format(['name' => 'John']);

        self::assertSame('Hello John!', $result);
    }

    #[Test]
    public function shouldFormatChatTemplate(): void
    {
        $messages = [
            ['role' => 'system', 'content' => 'You specialize in {{domain}}.'],
            ['role' => 'user', 'content' => '{{question}}'],
        ];

        $version = new PromptVersion(
            id: 'version-123',
            promptId: 'prompt-456',
            commit: 'abc123',
            template: $messages,
            templateStructure: TemplateStructure::CHAT,
        );

        $result = $version->format(['domain' => 'physics', 'question' => 'What is gravity?']);

        self::assertIsArray($result);
        self::assertCount(2, $result);
        self::assertSame('You specialize in physics.', $result[0]['content']);
        self::assertSame('What is gravity?', $result[1]['content']);
        self::assertSame('system', $result[0]['role']);
        self::assertSame('user', $result[1]['role']);
    }

    #[Test]
    public function shouldCreateFromArrayWithTextStructure(): void
    {
        $data = [
            'id' => 'version-123',
            'prompt_id' => 'prompt-456',
            'commit' => 'abc123',
            'template' => 'Hello {{name}}!',
            'type' => 'text',
            'template_structure' => 'text',
        ];

        $version = PromptVersion::fromArray($data);

        self::assertSame('version-123', $version->id);
        self::assertSame('Hello {{name}}!', $version->template);
        self::assertSame(TemplateStructure::TEXT, $version->templateStructure);
    }

    #[Test]
    public function shouldCreateFromArrayWithChatStructure(): void
    {
        $messages = [
            ['role' => 'system', 'content' => 'You are helpful.'],
            ['role' => 'user', 'content' => '{{question}}'],
        ];

        $data = [
            'id' => 'version-123',
            'prompt_id' => 'prompt-456',
            'commit' => 'abc123',
            'template' => json_encode($messages),
            'type' => 'text',
            'template_structure' => 'chat',
        ];

        $version = PromptVersion::fromArray($data);

        self::assertSame(TemplateStructure::CHAT, $version->templateStructure);
        self::assertIsArray($version->template);
        self::assertCount(2, $version->template);
        self::assertSame('You are helpful.', $version->template[0]['content']);
    }

    #[Test]
    public function shouldDefaultToTextStructure(): void
    {
        $data = [
            'id' => 'version-123',
            'prompt_id' => 'prompt-456',
            'commit' => 'abc123',
            'template' => 'Hello!',
        ];

        $version = PromptVersion::fromArray($data);

        self::assertSame(TemplateStructure::TEXT, $version->templateStructure);
    }

    #[Test]
    public function shouldConvertToArray(): void
    {
        $version = new PromptVersion(
            id: 'version-123',
            promptId: 'prompt-456',
            commit: 'abc123',
            template: 'Hello!',
            type: PromptType::TEXT,
            templateStructure: TemplateStructure::TEXT,
        );

        $array = $version->toArray();

        self::assertSame([
            'id' => 'version-123',
            'prompt_id' => 'prompt-456',
            'commit' => 'abc123',
            'template' => 'Hello!',
            'type' => 'text',
            'template_structure' => 'text',
        ], $array);
    }
}
