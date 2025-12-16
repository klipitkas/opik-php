<?php

declare(strict_types=1);

namespace Opik\Prompt;

/**
 * Represents a single message in a chat prompt.
 *
 * Chat messages follow the OpenAI chat completion format with role and content.
 * Content can be a simple string or a structured array for multimodal content.
 *
 * @example
 * ```php
 * // Simple text message
 * $message = new ChatMessage(role: ChatMessageRole::USER, content: 'Hello {{name}}!');
 *
 * // System message using factory method
 * $message = ChatMessage::system('You are a helpful assistant.');
 *
 * // User message with multimodal content
 * $message = ChatMessage::user([
 *     ['type' => 'text', 'text' => 'What is in this image?'],
 *     ['type' => 'image_url', 'image_url' => ['url' => '{{image_path}}']],
 * ]);
 * ```
 */
final class ChatMessage
{
    /**
     * @param ChatMessageRole $role The message role
     * @param string|array<int, array<string, mixed>> $content The message content
     */
    public function __construct(
        public readonly ChatMessageRole $role,
        public readonly string|array $content,
    ) {
    }

    /**
     * Create a system message.
     *
     * @param string|array<int, array<string, mixed>> $content The message content
     */
    public static function system(string|array $content): self
    {
        return new self(role: ChatMessageRole::SYSTEM, content: $content);
    }

    /**
     * Create a user message.
     *
     * @param string|array<int, array<string, mixed>> $content The message content
     */
    public static function user(string|array $content): self
    {
        return new self(role: ChatMessageRole::USER, content: $content);
    }

    /**
     * Create an assistant message.
     *
     * @param string|array<int, array<string, mixed>> $content The message content
     */
    public static function assistant(string|array $content): self
    {
        return new self(role: ChatMessageRole::ASSISTANT, content: $content);
    }

    /**
     * Create a tool message.
     *
     * @param string|array<int, array<string, mixed>> $content The message content
     */
    public static function tool(string|array $content): self
    {
        return new self(role: ChatMessageRole::TOOL, content: $content);
    }

    /**
     * Convert to array representation.
     *
     * @return array{role: string, content: string|array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'role' => $this->role->value,
            'content' => $this->content,
        ];
    }

    /**
     * Create from array representation.
     *
     * @param array{role: string, content: string|array<int, array<string, mixed>>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            role: ChatMessageRole::from($data['role']),
            content: $data['content'],
        );
    }
}
