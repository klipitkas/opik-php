<?php

declare(strict_types=1);

namespace Opik\Feedback;

enum FeedbackScoreSource: string
{
    case SDK = 'sdk';
    case UI = 'ui';
    case AUTOMATION = 'automation';
}
