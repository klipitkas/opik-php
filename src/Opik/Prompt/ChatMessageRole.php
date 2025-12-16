<?php

declare(strict_types=1);

namespace Opik\Prompt;

/**
 * Roles for chat messages.
 *
 * These roles follow the OpenAI chat completion format.
 */
enum ChatMessageRole: string
{
    case SYSTEM = 'system';
    case USER = 'user';
    case ASSISTANT = 'assistant';
    case TOOL = 'tool';
}
