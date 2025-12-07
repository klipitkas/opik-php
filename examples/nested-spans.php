<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Opik\OpikClient;
use Opik\Tracer\SpanType;

// Configure for cloud usage via environment variables
// Set: OPIK_API_KEY, OPIK_WORKSPACE, OPIK_PROJECT_NAME
$client = new OpikClient();

$trace = $client->trace(
    name: 'multi-step-agent',
    input: ['query' => 'What is the weather in Paris?'],
);

$orchestratorSpan = $trace->span(
    name: 'orchestrator',
    type: SpanType::GENERAL,
    input: ['query' => 'What is the weather in Paris?'],
);

$parseSpan = $orchestratorSpan->span(
    name: 'parse-intent',
    type: SpanType::TOOL,
    input: ['query' => 'What is the weather in Paris?'],
);
$parseSpan->update(output: [
    'intent' => 'weather_query',
    'location' => 'Paris',
]);
$parseSpan->end();

$toolSpan = $orchestratorSpan->span(
    name: 'weather-api-call',
    type: SpanType::TOOL,
    input: ['location' => 'Paris'],
);
$toolSpan->update(output: [
    'temperature' => 18,
    'conditions' => 'partly cloudy',
    'humidity' => 65,
]);
$toolSpan->end();

$llmSpan = $orchestratorSpan->span(
    name: 'generate-response',
    type: SpanType::LLM,
    input: [
        'context' => [
            'temperature' => 18,
            'conditions' => 'partly cloudy',
        ],
        'query' => 'What is the weather in Paris?',
    ],
);
$llmSpan->update(
    output: [
        'response' => 'The weather in Paris is currently 18°C and partly cloudy with 65% humidity.',
    ],
    model: 'gpt-4',
    provider: 'openai',
);
$llmSpan->end();

$orchestratorSpan->update(output: [
    'response' => 'The weather in Paris is currently 18°C and partly cloudy with 65% humidity.',
]);
$orchestratorSpan->end();

$trace->update(output: [
    'response' => 'The weather in Paris is currently 18°C and partly cloudy with 65% humidity.',
]);
$trace->end();

$client->flush();

echo "Multi-step agent trace created!\n";
echo "Trace ID: {$trace->getId()}\n";
