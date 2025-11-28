<?php

declare(strict_types=1);

namespace Opik\Decorator;

use Attribute;
use Opik\Tracer\SpanType;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
final readonly class Track
{
    public function __construct(
        public ?string $name = null,
        public ?string $projectName = null,
        public SpanType $type = SpanType::GENERAL,
        public bool $captureInput = true,
        public bool $captureOutput = true,
    ) {}
}
