<?php

declare(strict_types=1);

namespace Opik\Message;

/**
 * Types of messages that can be sent to the batch queue.
 */
enum MessageType: string
{
    case CREATE_TRACE = 'create_trace';
    case UPDATE_TRACE = 'update_trace';
    case CREATE_SPAN = 'create_span';
    case UPDATE_SPAN = 'update_span';
    case ADD_FEEDBACK_SCORE = 'add_feedback_score';
}
