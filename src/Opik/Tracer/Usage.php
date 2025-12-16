<?php

declare(strict_types=1);

namespace Opik\Tracer;

final class Usage
{
    public readonly ?int $promptTokens;

    public readonly ?int $completionTokens;

    public readonly ?int $totalTokens;

    public function __construct(
        ?int $promptTokens = null,
        ?int $completionTokens = null,
        ?int $totalTokens = null,
    ) {
        $this->promptTokens = $promptTokens;
        $this->completionTokens = $completionTokens;
        $this->totalTokens = $totalTokens;
    }

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
