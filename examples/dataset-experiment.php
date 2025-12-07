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
    new DatasetItem(
        input: ['question' => 'What is machine learning?'],
        expectedOutput: ['answer' => 'Machine learning is a subset of AI that enables systems to learn from data.'],
        metadata: ['category' => 'ai'],
    ),
]);

echo "Dataset created with items\n";

$experiment = $client->createExperiment(
    name: 'php-sdk-experiment-' . date('Y-m-d-H-i-s'),
    datasetName: $dataset->name,
);

echo "Experiment created: {$experiment->name}\n";

$items = $dataset->getItems();
$experimentItems = [];

foreach ($items as $item) {
    $trace = $client->trace(
        name: 'qa-evaluation',
        input: $item->input,
    );

    $span = $trace->span(
        name: 'generate-answer',
        type: SpanType::LLM,
        input: $item->input,
    );

    $generatedAnswer = "This is a mock answer for: {$item->input['question']}";

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
