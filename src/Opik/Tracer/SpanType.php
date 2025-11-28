<?php

declare(strict_types=1);

namespace Opik\Tracer;

/**
 * Types of spans that can be created in a trace.
 */
enum SpanType: string
{
    case LLM = 'llm';
    case TOOL = 'tool';
    case GENERAL = 'general';
    case GUARDRAIL = 'guardrail';
}
