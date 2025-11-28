<?php

declare(strict_types=1);

namespace Opik\Prompt;

final readonly class PromptVersion
{
    /**
     * @param string|array<int, array<string, mixed>> $template
     */
    public function __construct(
        public string $id,
        public string $promptId,
        public string $commit,
        public string|array $template,
        public PromptType $type = PromptType::TEXT,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            promptId: $data['prompt_id'],
            commit: $data['commit'],
            template: $data['template'],
            type: isset($data['type']) ? PromptType::from($data['type']) : PromptType::TEXT,
        );
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function format(array $variables = []): string|array
    {
        if (\is_string($this->template)) {
            return $this->formatString($this->template, $variables);
        }

        return $this->formatChat($this->template, $variables);
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function formatString(string $template, array $variables): string
    {
        $result = $template;

        foreach ($variables as $key => $value) {
            $result = \str_replace(
                ["{{$key}}", "{{ $key }}"],
                (string) $value,
                $result,
            );
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @param array<string, mixed> $variables
     * @return array<int, array<string, mixed>>
     */
    private function formatChat(array $messages, array $variables): array
    {
        return \array_map(
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
        ];
    }
}
