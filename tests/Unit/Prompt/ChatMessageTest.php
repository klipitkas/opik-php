<?php

declare(strict_types=1);

namespace Opik\Tests\Unit\Prompt;

use Opik\Prompt\ChatMessage;
use Opik\Prompt\ChatMessageRole;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ChatMessageTest extends TestCase
{
    #[Test]
    public function shouldCreateMessageWithRole(): void
    {
        $message = new ChatMessage(
            role: ChatMessageRole::USER,
            content: 'Hello!',
        );

        self::assertSame(ChatMessageRole::USER, $message->role);
        self::assertSame('Hello!', $message->content);
    }

    #[Test]
    public function shouldCreateSystemMessage(): void
    {
        $message = ChatMessage::system('You are a helpful assistant.');

        self::assertSame(ChatMessageRole::SYSTEM, $message->role);
        self::assertSame('You are a helpful assistant.', $message->content);
    }

    #[Test]
    public function shouldCreateUserMessage(): void
    {
        $message = ChatMessage::user('What is PHP?');

        self::assertSame(ChatMessageRole::USER, $message->role);
        self::assertSame('What is PHP?', $message->content);
    }

    #[Test]
    public function shouldCreateAssistantMessage(): void
    {
        $message = ChatMessage::assistant('PHP is a programming language.');

        self::assertSame(ChatMessageRole::ASSISTANT, $message->role);
        self::assertSame('PHP is a programming language.', $message->content);
    }

    #[Test]
    public function shouldCreateToolMessage(): void
    {
        $message = ChatMessage::tool('Tool result');

        self::assertSame(ChatMessageRole::TOOL, $message->role);
        self::assertSame('Tool result', $message->content);
    }

    #[Test]
    public function shouldCreateMessageWithArrayContent(): void
    {
        $content = [
            ['type' => 'text', 'text' => 'What is in this image?'],
            ['type' => 'image_url', 'image_url' => ['url' => 'https://example.com/image.jpg']],
        ];

        $message = ChatMessage::user($content);

        self::assertSame(ChatMessageRole::USER, $message->role);
        self::assertSame($content, $message->content);
    }

    #[Test]
    public function shouldConvertToArray(): void
    {
        $message = ChatMessage::user('Hello {{name}}!');

        $array = $message->toArray();

        self::assertSame([
            'role' => 'user',
            'content' => 'Hello {{name}}!',
        ], $array);
    }

    #[Test]
    public function shouldConvertArrayContentToArray(): void
    {
        $content = [
            ['type' => 'text', 'text' => 'Describe this image'],
        ];

        $message = ChatMessage::user($content);
        $array = $message->toArray();

        self::assertSame([
            'role' => 'user',
            'content' => $content,
        ], $array);
    }

    #[Test]
    public function shouldCreateFromArray(): void
    {
        $data = [
            'role' => 'assistant',
            'content' => 'I can help with that.',
        ];

        $message = ChatMessage::fromArray($data);

        self::assertSame(ChatMessageRole::ASSISTANT, $message->role);
        self::assertSame('I can help with that.', $message->content);
    }

    #[Test]
    public function shouldCreateFromArrayWithArrayContent(): void
    {
        $content = [
            ['type' => 'text', 'text' => 'Test content'],
        ];

        $data = [
            'role' => 'user',
            'content' => $content,
        ];

        $message = ChatMessage::fromArray($data);

        self::assertSame(ChatMessageRole::USER, $message->role);
        self::assertSame($content, $message->content);
    }
}
