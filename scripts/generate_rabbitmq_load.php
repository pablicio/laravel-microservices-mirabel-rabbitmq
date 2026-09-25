<?php

declare(strict_types=1);

define('APP_ROOT', __DIR__ . '/..');

$options = getopt('', ['service:', 'iterations::', 'delay-ms::', 'error-rate::', 'reset', 'help']);

if (isset($options['help']) || !isset($options['service'])) {
    echo "Usage: php scripts/generate_rabbitmq_load.php --service=store-service --iterations=200 --delay-ms=100 --error-rate=0.15 [--reset]\n";
    exit(0);
}

$service = (string) $options['service'];
$iterations = max(1, (int) ($options['iterations'] ?? 200));
$delayMs = max(0, (int) ($options['delay-ms'] ?? 100));
$errorRate = min(1, max(0, (float) ($options['error-rate'] ?? 0.15)));
$servicePath = APP_ROOT . '/' . $service;

if (!is_dir($servicePath)) {
    fwrite(STDERR, "Service directory not found: {$servicePath}\n");
    exit(1);
}

require $servicePath . '/vendor/autoload.php';
$app = require $servicePath . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$metrics = $app->make(App\Support\RabbitMqMetrics::class);

if (isset($options['reset'])) {
    $metrics->reset();
}

$events = ['published', 'processed', 'duplicate_message'];
$generated = [];

for ($iteration = 0; $iteration < $iterations; $iteration++) {
    $event = random_int(1, 100) / 100 <= $errorRate
        ? 'message_retried'
        : $events[array_rand($events)];

    $metrics->record($event);
    $generated[$event] = ($generated[$event] ?? 0) + 1;

    if ($delayMs > 0) {
        usleep($delayMs * 1000);
    }
}

if (($generated['message_retried'] ?? 0) > 0) {
    $metrics->record('message_sent_to_error_queue');
    $generated['message_sent_to_error_queue'] = 1;
}

echo "Generated {$iterations} events for {$service}.\n";
echo json_encode($generated, JSON_PRETTY_PRINT) . "\n";
echo $metrics->renderPrometheus();
