<?php

declare(strict_types=1);

namespace Opik\Attachment;

/**
 * Entity types that can have attachments.
 */
enum AttachmentEntityType: string
{
    case TRACE = 'trace';
    case SPAN = 'span';
}
