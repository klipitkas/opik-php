<?php

declare(strict_types=1);

namespace Opik\Tracer;

final readonly class Usage
{
    public function __construct(
        public ?int $promptTokens = null,
        public ?int $completionTokens = null,
        public ?int $totalTokens = null,
    ) {}

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->promptTokens !== null) {
            $data['prompt_tokens'] = $this->promptTokens;
        }

        if ($this->completionTokens !== null) {
            $data['completion_tokens'] = $this->completionTokens;
        }

        if ($this->totalTokens !== null) {
            $data['total_tokens'] = $this->totalTokens;
        }

        return $data;
    }
}
