<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Opik\Dataset\DatasetItem;
use Opik\Experiment\ExperimentItem;
use Opik\OpikClient;
use Opik\Tracer\SpanType;

// Configure for cloud usage via environment variables
// Set: OPIK_API_KEY, OPIK_WORKSPACE, OPIK_PROJECT_NAME
$client = new OpikClient();

$dataset = $client->getOrCreateDataset(
    name: 'php-sdk-qa-dataset',
    description: 'Q&A pairs for testing the PHP SDK',
);

// Insert items using standard fields (input, expectedOutput, metadata)
$dataset->insert([
    new DatasetItem(
        input: ['question' => 'What is PHP?'],
        expectedOutput: ['answer' => 'PHP is a general-purpose scripting language.'],
        metadata: ['category' => 'programming'],
    ),
    new DatasetItem(
        input: ['question' => 'What is Opik?'],
        expectedOutput: ['answer' => 'Opik is an LLM observability and evaluation platform.'],
        metadata: ['category' => 'tools'],
    ),
]);

// Insert items using arbitrary fields (flexible schema - matches Python/TypeScript SDKs)
$dataset->insert([
    new DatasetItem(
        data: [
            'prompt' => 'Explain machine learning in simple terms',
            'context' => 'Educational content for beginners',
            'difficulty' => 'easy',
            'expected_response' => 'Machine learning is a way for computers to learn from examples.',
            'tags' => ['ai', 'education', 'beginner'],
        ],
    ),
    new DatasetItem(
        data: [
            'prompt' => 'Write a haiku about coding',
            'style' => 'poetry',
            'constraints' => ['5-7-5 syllable pattern', 'theme: programming'],
            'reference_output' => "Code flows like water\nBugs appear then disappear\nTests finally pass",
        ],
    ),
]);

echo "Dataset created with items (standard and arbitrary fields)\n";

$experiment = $client->createExperiment(
    name: 'php-sdk-experiment-' . date('Y-m-d-H-i-s'),
    datasetName: $dataset->name,
);

echo "Experiment created: {$experiment->name}\n";

$items = $dataset->getItems();
$experimentItems = [];

foreach ($items as $item) {
    // Get content using the flexible getContent() method
    $content = $item->getContent();

    // Determine input based on whether it's standard or arbitrary format
    $inputText = $item->getInput()['question']
        ?? $content['prompt']
        ?? 'Unknown input';

    $trace = $client->trace(
        name: 'qa-evaluation',
        input: $content,
    );

    $span = $trace->span(
        name: 'generate-answer',
        type: SpanType::LLM,
        input: $content,
    );

    $generatedAnswer = "This is a mock answer for: {$inputText}";

    $span->update(
        output: ['answer' => $generatedAnswer],
        model: 'gpt-4',
        provider: 'openai',
    );
    $span->end();

    $trace->update(output: ['answer' => $generatedAnswer]);
    $trace->end();

    $experimentItems[] = new ExperimentItem(
        datasetItemId: $item->id,
        traceId: $trace->getId(),
        output: ['answer' => $generatedAnswer],
        feedbackScores: [
            ['name' => 'mock_accuracy', 'value' => 0.85],
        ],
    );
}

$experiment->logItems($experimentItems);

echo "Experiment items logged\n";

$client->flush();

echo "Done! Check the Opik dashboard to see your dataset and experiment.\n";
