<?php

declare(strict_types=1);

namespace Opik\Dataset;

/**
 * Source types for dataset items.
 */
enum DatasetItemSource: string
{
    case MANUAL = 'manual';
    case TRACE = 'trace';
    case SPAN = 'span';
    case SDK = 'sdk';
}
