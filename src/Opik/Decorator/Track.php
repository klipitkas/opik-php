<?php

declare(strict_types=1);

namespace Opik\Decorator;

use Attribute;
use Opik\Tracer\SpanType;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
final class Track
{
    public readonly ?string $name;

    public readonly ?string $projectName;

    public readonly SpanType $type;

    public readonly bool $captureInput;

    public readonly bool $captureOutput;

    public function __construct(
        ?string $name = null,
        ?string $projectName = null,
        SpanType $type = SpanType::GENERAL,
        bool $captureInput = true,
        bool $captureOutput = true,
    ) {
        $this->name = $name;
        $this->projectName = $projectName;
        $this->type = $type;
        $this->captureInput = $captureInput;
        $this->captureOutput = $captureOutput;
    }
}
