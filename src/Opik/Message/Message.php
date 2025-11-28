<?php

declare(strict_types=1);

namespace Opik\Message;

final readonly class Message
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public MessageType $type,
        public array $data,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'data' => $this->data,
        ];
    }
}
