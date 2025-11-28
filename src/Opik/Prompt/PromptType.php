<?php

declare(strict_types=1);

namespace Opik\Prompt;

/**
 * Types of prompts.
 */
enum PromptType: string
{
    case TEXT = 'text';
    case CHAT = 'chat';
}
