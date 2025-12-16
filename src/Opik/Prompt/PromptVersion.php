<?php

declare(strict_types=1);

namespace Opik\Prompt;

/**
 * Represents a specific version of a prompt.
 *
 * PromptVersion handles both text and chat prompt formats.
 * For text prompts, the template is a string with {{variable}} placeholders.
 * For chat prompts, the template is an array of message objects with role and content.
 */
final class PromptVersion
{
    public readonly string $id;

    public readonly string $promptId;

    public readonly string $commit;

    /** @var string|array<int, array<string, mixed>> */
    public readonly string|array $template;

    public readonly PromptType $type;

    public readonly TemplateStructure $templateStructure;

    /**
     * @param string|array<int, array<string, mixed>> $template
     */
    public function __construct(
        string $id,
        string $promptId,
        string $commit,
        string|array $template,
        PromptType $type = PromptType::TEXT,
        TemplateStructure $templateStructure = TemplateStructure::TEXT,
    ) {
        $this->id = $id;
        $this->promptId = $promptId;
        $this->commit = $commit;
        $this->template = $template;
        $this->type = $type;
        $this->templateStructure = $templateStructure;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $templateStructure = isset($data['template_structure'])
            ? TemplateStructure::from($data['template_structure'])
            : TemplateStructure::TEXT;

        $template = $data['template'];

        // For chat prompts, the template is stored as JSON string in the API
        // We need to decode it to get the messages array
        if ($templateStructure === TemplateStructure::CHAT && \is_string($template)) {
            $decoded = json_decode($template, true);
            if (\is_array($decoded)) {
                $template = $decoded;
            }
        }

        return new self(
            id: $data['id'],
            promptId: $data['prompt_id'],
            commit: $data['commit'],
            template: $template,
            type: isset($data['type']) ? PromptType::from($data['type']) : PromptType::TEXT,
            templateStructure: $templateStructure,
        );
    }

    /**
     * Format the template with the provided variables.
     *
     * For text prompts, returns a string with variables replaced.
     * For chat prompts, returns an array of messages with variables replaced.
     *
     * @param array<string, mixed> $variables
     *
     * @return string|array<int, array<string, mixed>>
     */
    public function format(array $variables = []): string|array
    {
        if ($this->templateStructure === TemplateStructure::CHAT && \is_array($this->template)) {
            return $this->formatChat($this->template, $variables);
        }

        if (\is_string($this->template)) {
            return $this->formatString($this->template, $variables);
        }

        // Fallback: if template is array but structure is text, format as chat
        return $this->formatChat($this->template, $variables);
    }

    /**
     * Check if this is a chat prompt.
     */
    public function isChat(): bool
    {
        return $this->templateStructure === TemplateStructure::CHAT;
    }

    /**
     * Check if this is a text prompt.
     */
    public function isText(): bool
    {
        return $this->templateStructure === TemplateStructure::TEXT;
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function formatString(string $template, array $variables): string
    {
        $result = $template;

        foreach ($variables as $key => $value) {
            $result = str_replace(
                ['{{' . $key . '}}', '{{ ' . $key . ' }}'],
                (string) $value,
                $result,
            );
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @param array<string, mixed> $variables
     *
     * @return array<int, array<string, mixed>>
     */
    private function formatChat(array $messages, array $variables): array
    {
        return array_map(
            function (array $message) use ($variables): array {
                if (isset($message['content']) && \is_string($message['content'])) {
                    $message['content'] = $this->formatString($message['content'], $variables);
                }

                return $message;
            },
            $messages,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'prompt_id' => $this->promptId,
            'commit' => $this->commit,
            'template' => $this->template,
            'type' => $this->type->value,
            'template_structure' => $this->templateStructure->value,
        ];
    }
}
