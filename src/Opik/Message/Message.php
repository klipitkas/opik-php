<?php

declare(strict_types=1);

namespace Opik\Message;

final class Message
{
    public readonly MessageType $type;

    /** @var array<string, mixed> */
    public readonly array $data;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        MessageType $type,
        array $data,
    ) {
        $this->type = $type;
        $this->data = $data;
    }

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
