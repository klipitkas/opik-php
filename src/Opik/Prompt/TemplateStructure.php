<?php

declare(strict_types=1);

namespace Opik\Prompt;

/**
 * Template structure types for prompts.
 *
 * TEXT: Simple string template with {{variable}} placeholders.
 * CHAT: Array of messages with role and content fields.
 */
enum TemplateStructure: string
{
    case TEXT = 'text';
    case CHAT = 'chat';
}
