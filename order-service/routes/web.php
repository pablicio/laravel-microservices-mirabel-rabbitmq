<?php

use App\Support\ConsumerPoolManager;
use App\Support\RabbitMqMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('load-console', [
        'metrics' => app(RabbitMqMetrics::class)->snapshot(),
        'batch' => app(RabbitMqMetrics::class)->batch(),
    ]);
});

Route::get('/applications-test', function () {
    return view('applications-test');
});

Route::get('/black-friday', function () {
    return view('black-friday', [
        'products' => [
            ['id' => 'headset', 'name' => 'Pulse Headset', 'category' => 'Audio', 'price' => 8990, 'stock' => 4, 'accent' => '#d85d4c'],
            ['id' => 'keyboard', 'name' => 'Atlas Keyboard', 'category' => 'Desk', 'price' => 12990, 'stock' => 7, 'accent' => '#176b87'],
            ['id' => 'camera', 'name' => 'Orbit Camera', 'category' => 'Creator', 'price' => 21990, 'stock' => 2, 'accent' => '#8b6f47'],
        ],
    ]);
});

Route::get('/stress-test', function (RabbitMqMetrics $metrics) {
    return view('stress-test', [
        'stress' => $metrics->stress(),
        'history' => $metrics->stressHistory(),
    ]);
});

Route::get('/production-readiness', function () {
    return view('production-readiness');
});

Route::get('/consumer-lab', function () {
    return view('consumer-lab', ['history' => app(RabbitMqMetrics::class)->consumerHistory()]);
});

Route::post('/consumer-lab/start', function (Request $request) {
    $data = $request->validate([
        'messages' => ['required', 'integer', 'min:1', 'max:100000'],
        'consumers' => ['required', 'integer', 'min:1', 'max:8'],
    ]);
    $runId = bin2hex(random_bytes(8));
    $storeArtisan = realpath(base_path('../store-service/artisan'));
    $php = PHP_BINARY;
    try {
        $storeMetrics = Http::timeout(1)->get('http://127.0.0.1:8000/rabbitmq/metrics')->body();
    } catch (\Throwable) {
        $storeMetrics = '';
    }
    $processedBaseline = 0;
    if (preg_match('/rabbitmq_messages_total\{event="processed"\}\s+(\d+)/', $storeMetrics, $matches)) {
        $processedBaseline = (int) $matches[1];
    }

    $queueUrl = 'http://127.0.0.1:15672/api/queues/%2F/store-services.orders.created';
    $getActiveConsumers = static function () use ($queueUrl): ?int {
        try {
            $response = Http::withBasicAuth(
                env('MB_RABBITMQ_USER', 'guest'),
                env('MB_RABBITMQ_PASSWORD', 'guest'),
            )->timeout(2)->get($queueUrl);
            $count = $response->json('consumers');

            return $response->successful() && is_numeric($count) ? (int) $count : null;
        } catch (\Throwable) {
            return null;
        }
    };
    $startWorker = static function () use ($php, $storeArtisan): void {
        if (PHP_OS_FAMILY === 'Windows') {
            $command = sprintf('start /B "" "%s" "%s" rabbitmq:consume-store-orders > NUL 2>&1', $php, $storeArtisan);
        } else {
            $command = sprintf('"%s" "%s" rabbitmq:consume-store-orders > /dev/null 2>&1 &', $php, $storeArtisan);
        }
        pclose(popen($command, 'r'));
    };

    try {
        app(ConsumerPoolManager::class)->reconcile((int) $data['consumers'], $storeArtisan, $getActiveConsumers, $startWorker);
    } catch (\RuntimeException $exception) {
        return back()->withErrors(['consumers' => $exception->getMessage()]);
    }

    $arguments = sprintf('rabbitmq:stress %d --users=%d --processed-baseline=%d --run-id=%s --consumers=%d', (int) $data['messages'], (int) $data['consumers'] * 100, $processedBaseline, $runId, (int) $data['consumers']);
    $command = sprintf('start /B "" "%s" "%s" %s > NUL 2>&1', $php, base_path('artisan'), $arguments);
    pclose(popen($command, 'r'));

    return redirect('/consumer-lab')->with('consumer_run_id', $runId);
});

Route::get('/consumer-lab/status', function (Request $request, RabbitMqMetrics $metrics) {
    $runId = (string) $request->query('run_id', '');
    $publisher = $metrics->stress();
    $processedTotal = 0;
    $storeMetrics = Http::timeout(1)->get('http://127.0.0.1:8000/rabbitmq/metrics')->body();
    if (preg_match('/rabbitmq_messages_total\{event="processed"\}\s+(\d+)/', $storeMetrics, $matches)) {
        $processedTotal = (int) $matches[1];
    }
    $processedBaseline = (int) ($publisher['processed_baseline'] ?? 0);
    $processed = max(0, $processedTotal - $processedBaseline);
    $status = ($publisher['run_id'] ?? '') === $runId ? ($publisher['status'] ?? 'running') : 'queued';
    try {
        $queue = Http::withBasicAuth(
            env('MB_RABBITMQ_USER', 'guest'),
            env('MB_RABBITMQ_PASSWORD', 'guest'),
        )->timeout(1)->get('http://127.0.0.1:15672/api/queues/%2F/store-services.orders.created')->json();
    } catch (\Throwable) {
        $queue = [];
    }

    $result = [
        'run_id' => $runId,
        'status' => $status,
        'requested' => ($publisher['run_id'] ?? '') === $runId ? ($publisher['requested'] ?? 0) : 0,
        'published' => ($publisher['run_id'] ?? '') === $runId ? ($publisher['published'] ?? 0) : 0,
        'requested_consumers' => ($publisher['run_id'] ?? '') === $runId ? ($publisher['requested_consumers'] ?? 0) : 0,
        'processed' => $processed,
        'backlog' => (int) ($queue['messages'] ?? 0),
        'consumers' => (int) ($queue['consumers'] ?? 0),
        'elapsed_ms' => ($publisher['run_id'] ?? '') === $runId ? ($publisher['elapsed_ms'] ?? 0) : 0,
        'consumer_elapsed_ms' => ($publisher['run_id'] ?? '') === $runId
            ? max(0, (int) round(microtime(true) * 1000) - (int) ($publisher['started_at_ms'] ?? 0))
            : 0,
    ];
    $result['difference'] = (int) $result['published'] - $processed;
    if ($status === 'completed' && (int) $result['backlog'] === 0) {
        $result['status'] = $processed === (int) $result['published'] ? 'completed' : 'incomplete';
        $result['throughput'] = $result['status'] === 'completed' && (int) $result['consumer_elapsed_ms'] > 0
            ? round($processed / ($result['consumer_elapsed_ms'] / 1000), 2)
            : 0;
        $result['completed_at'] = now()->toIso8601String();
        $metrics->saveConsumerRun($result);
    }

    return response()->json($result);
});

Route::post('/production-readiness/evaluate', function (Request $request) {
    $data = $request->validate([
        'messages' => ['required', 'integer', 'min:1000', 'max:1000000'],
        'users' => ['required', 'integer', 'min:1', 'max:100000'],
        'publishers' => ['required', 'integer', 'min:1', 'max:8'],
        'consumers' => ['required', 'integer', 'min:1', 'max:8'],
        'message_rate' => ['required', 'numeric', 'min:1', 'max:50000'],
        'consumer_rate' => ['required', 'numeric', 'min:100', 'max:20000'],
        'p95_ms' => ['required', 'numeric', 'min:1', 'max:10000'],
        'failure_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        'confirms' => ['nullable', 'boolean'],
        'failover' => ['nullable', 'boolean'],
    ]);

    $publisherCapacity = 7872 * (int) $data['publishers'];
    $consumerCapacity = (float) $data['consumer_rate'] * (int) $data['consumers'];
    $backlogPerSecond = max(0, (float) $data['message_rate'] - $consumerCapacity);
    $durationSeconds = (int) ceil((int) $data['messages'] / max(1, min($publisherCapacity, (float) $data['message_rate'])));
    $checks = [];
    $score = 100;

    $checks[] = [
        'name' => 'Consumidores acompanham a entrada',
        'status' => $consumerCapacity >= (float) $data['message_rate'] ? 'pass' : 'fail',
        'detail' => number_format($consumerCapacity, 0, ',', '.') . ' msg/s de consumo contra ' . number_format((float) $data['message_rate'], 0, ',', '.') . ' msg/s de entrada.',
    ];
    $checks[] = [
        'name' => 'Publisher suporta a meta',
        'status' => $publisherCapacity >= (float) $data['message_rate'] ? 'pass' : 'fail',
        'detail' => number_format($publisherCapacity, 0, ',', '.') . ' msg/s estimadas com ' . $data['publishers'] . ' publisher(s).',
    ];
    $checks[] = [
        'name' => 'Publisher confirms',
        'status' => !empty($data['confirms']) ? 'pass' : 'warn',
        'detail' => !empty($data['confirms']) ? 'O broker confirma a entrega.' : 'Sem confirmação, a entrega não tem a mesma garantia operacional.',
    ];
    $checks[] = [
        'name' => 'Latência p95',
        'status' => (float) $data['p95_ms'] <= 250 ? 'pass' : 'warn',
        'detail' => 'p95 observado: ' . $data['p95_ms'] . ' ms.',
    ];
    $checks[] = [
        'name' => 'Taxa de falha',
        'status' => (float) $data['failure_rate'] <= 1 ? 'pass' : 'fail',
        'detail' => 'Falhas estimadas: ' . $data['failure_rate'] . '%.',
    ];
    $checks[] = [
        'name' => 'Teste de failover',
        'status' => !empty($data['failover']) ? 'pass' : 'warn',
        'detail' => !empty($data['failover']) ? 'A recuperação foi exercitada.' : 'Ainda falta simular queda do broker ou da rede.',
    ];

    foreach ($checks as $check) {
        $score -= $check['status'] === 'fail' ? 25 : ($check['status'] === 'warn' ? 10 : 0);
    }
    $verdict = $score >= 80 && !collect($checks)->contains('status', 'fail') ? 'production_candidate' : ($score >= 55 ? 'pilot' : 'not_ready');

    return redirect('/production-readiness')->with('readiness', [
        'score' => max(0, $score),
        'verdict' => $verdict,
        'messages' => (int) $data['messages'],
        'users' => (int) $data['users'],
        'duration_seconds' => $durationSeconds,
        'publisher_capacity' => $publisherCapacity,
        'consumer_capacity' => $consumerCapacity,
        'backlog_per_second' => $backlogPerSecond,
        'checks' => $checks,
    ]);
});

Route::post('/stress-test/start', function (Request $request) {
    $data = $request->validate([
        'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        'users' => ['required', 'integer', 'min:1', 'max:5000'],
    ]);
    $metrics = app(RabbitMqMetrics::class);
    if (($metrics->stress()['status'] ?? null) === 'running') {
        return redirect('/stress-test')->withErrors(['quantity' => 'Já existe um stress test em execução. Aguarde a finalização.']);
    }

    $runId = bin2hex(random_bytes(8));
    $artisan = base_path('artisan');
    $php = PHP_BINARY;
    $arguments = sprintf('rabbitmq:stress %d --users=%d --run-id=%s', (int) $data['quantity'], (int) $data['users'], $runId);

    if (PHP_OS_FAMILY === 'Windows') {
        $command = sprintf('start /B "" "%s" "%s" %s > NUL 2>&1', $php, $artisan, $arguments);
    } else {
        $command = sprintf('"%s" "%s" %s > /dev/null 2>&1 &', $php, $artisan, $arguments);
    }

    pclose(popen($command, 'r'));

    return redirect('/stress-test')->with('stress_run_id', $runId);
})->middleware('throttle:5,1');

Route::get('/stress-test/status', function (Request $request, RabbitMqMetrics $metrics) {
    $stress = $metrics->stress();
    $runId = (string) $request->query('run_id', '');

    if ($runId !== '' && ($stress['run_id'] ?? '') !== $runId) {
        return response()->json(['run_id' => $runId, 'status' => 'queued', 'requested' => 0, 'users' => 0, 'published' => 0, 'failed' => 0]);
    }

    return response()->json($stress + ['status' => 'idle', 'requested' => 0, 'users' => 0, 'published' => 0, 'failed' => 0]);
});

Route::post('/black-friday/checkout', function (Request $request) {
    $products = [
        'headset' => ['name' => 'Pulse Headset', 'price' => 8990, 'stock' => 4],
        'keyboard' => ['name' => 'Atlas Keyboard', 'price' => 12990, 'stock' => 7],
        'camera' => ['name' => 'Orbit Camera', 'price' => 21990, 'stock' => 2],
    ];
    $items = $request->validate(['items' => ['required', 'array']])['items'];
    $accepted = [];
    $blocked = [];
    $total = 0;

    foreach ($items as $id => $quantity) {
        if (!isset($products[$id]) || (int) $quantity < 1) {
            continue;
        }
        $quantity = (int) $quantity;
        if ($quantity > $products[$id]['stock']) {
            $blocked[] = [
                'name' => $products[$id]['name'],
                'requested' => $quantity,
                'available' => $products[$id]['stock'],
            ];
            continue;
        }
        $accepted[] = ['name' => $products[$id]['name'], 'quantity' => $quantity];
        $total += $products[$id]['price'] * $quantity;
    }

    return response()->json([
        'status' => count($blocked) > 0 ? 'partial' : 'approved',
        'accepted' => $accepted,
        'blocked' => $blocked,
        'total' => number_format($total / 100, 2, ',', '.'),
    ]);
});

Route::post('/applications-test/simulate', function (Request $request) {
    $data = $request->validate([
        'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        'failure_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        'duplicate_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        'max_attempts' => ['required', 'integer', 'min:1', 'max:5'],
    ]);
    $quantity = (int) $data['quantity'];
    $failureRate = (float) $data['failure_rate'];
    $duplicateRate = (float) $data['duplicate_rate'];
    $maxAttempts = (int) $data['max_attempts'];
    $summary = [
        'processed' => 0,
        'retried' => 0,
        'duplicates' => 0,
        'error_queue' => 0,
    ];
    $sample = [];

    for ($index = 1; $index <= $quantity; $index++) {
        $orderId = 'sim-order-' . str_pad((string) $index, 4, '0', STR_PAD_LEFT);
        $attempts = 0;
        $outcome = 'processed';

        if (random_int(1, 10000) <= (int) ($duplicateRate * 100)) {
            $summary['duplicates']++;
            $outcome = 'duplicate';
        } else {
            do {
                $attempts++;
                $failed = random_int(1, 10000) <= (int) ($failureRate * 100);
            } while ($failed && $attempts < $maxAttempts);

            if ($attempts > 1) {
                $summary['retried'] += $attempts - 1;
            }
            if ($failed) {
                $summary['error_queue']++;
                $outcome = 'error_queue';
            } else {
                $summary['processed']++;
            }
        }

        if (count($sample) < 12) {
            $sample[] = [
                'order_id' => $orderId,
                'attempts' => $attempts,
                'outcome' => $outcome,
            ];
        }
    }

    return redirect('/applications-test')->with('simulation', [
        'quantity' => $quantity,
        'failure_rate' => $failureRate,
        'duplicate_rate' => $duplicateRate,
        'max_attempts' => $maxAttempts,
        'summary' => $summary,
        'sample' => $sample,
    ]);
});

Route::post('/test/generate', function (Request $request) {
    $data = $request->validate([
        'quantity' => ['required', 'integer', 'min:1', 'max:500'],
        'delay_ms' => ['nullable', 'integer', 'min:0', 'max:5000'],
    ]);
    $quantity = (int) $data['quantity'];
    $delayMs = (int) ($data['delay_ms'] ?? 0);
    $runId = bin2hex(random_bytes(8));
    $artisan = base_path('artisan');
    $php = PHP_BINARY;
    $arguments = sprintf('rabbitmq:generate %d --delay-ms=%d --run-id=%s', $quantity, $delayMs, $runId);

    if (PHP_OS_FAMILY === 'Windows') {
        $command = sprintf('start /B "" "%s" "%s" %s > NUL 2>&1', $php, $artisan, $arguments);
    } else {
        $command = sprintf('"%s" "%s" %s > /dev/null 2>&1 &', $php, $artisan, $arguments);
    }

    pclose(popen($command, 'r'));

    return redirect('/')->with('generation', [
        'requested' => $quantity,
        'run_id' => $runId,
        'queued' => true,
    ]);
});

Route::get('/test/status', function (Request $request, RabbitMqMetrics $metrics) {
    $batch = $metrics->batch();
    $runId = (string) $request->query('run_id', '');

    if ($runId !== '' && ($batch['run_id'] ?? '') !== $runId) {
        return response()->json([
            'run_id' => $runId,
            'status' => 'queued',
            'requested' => 0,
            'published' => 0,
            'failed' => 0,
            'samples' => [],
            'metrics' => $metrics->snapshot(),
        ]);
    }

    return response()->json($batch + [
        'status' => 'idle',
        'requested' => 0,
        'published' => 0,
        'failed' => 0,
        'samples' => [],
        'metrics' => $metrics->snapshot(),
    ]);
});

Route::get('/rabbitmq/metrics', function (RabbitMqMetrics $metrics) {
    return response($metrics->renderPrometheus(), 200, [
        'Content-Type' => 'text/plain; version=0.0.4',
    ]);
});
