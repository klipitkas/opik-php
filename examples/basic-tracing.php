<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Opik\OpikClient;
use Opik\Tracer\SpanType;
use Opik\Tracer\Usage;

// Configure for cloud usage via environment variables
// Set: OPIK_API_KEY, OPIK_WORKSPACE, OPIK_PROJECT_NAME
$client = new OpikClient();

$trace = $client->trace(
    name: 'chat-completion-example',
    input: [
        'messages' => [
            ['role' => 'system', 'content' => 'You are a helpful assistant.'],
            ['role' => 'user', 'content' => 'What is PHP?'],
        ],
    ],
    metadata: ['environment' => 'development'],
    tags: ['example', 'php-sdk'],
);

$span = $trace->span(
    name: 'openai-chat-completion',
    type: SpanType::LLM,
    input: [
        'model' => 'gpt-4',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a helpful assistant.'],
            ['role' => 'user', 'content' => 'What is PHP?'],
        ],
        'temperature' => 0.7,
    ],
);

$response = 'PHP is a popular general-purpose scripting language that is especially suited to web development.';

$span->update(
    output: [
        'choices' => [
            [
                'message' => [
                    'role' => 'assistant',
                    'content' => $response,
                ],
            ],
        ],
    ],
    model: 'gpt-4',
    provider: 'openai',
    usage: new Usage(
        promptTokens: 25,
        completionTokens: 20,
        totalTokens: 45,
    ),
);
$span->end();

$span->logFeedbackScore(
    name: 'relevance',
    value: 0.95,
    reason: 'Response accurately describes PHP',
);

$trace->update(output: ['response' => $response]);
$trace->end();

$trace->logFeedbackScore(
    name: 'user_satisfaction',
    value: 'satisfied',
    categoryName: 'satisfied',
);

$client->flush();

echo "Trace created successfully!\n";
echo "Trace ID: {$trace->getId()}\n";
