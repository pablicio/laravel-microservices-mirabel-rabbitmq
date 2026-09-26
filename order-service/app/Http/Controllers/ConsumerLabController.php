<?php

namespace App\Http\Controllers;

use App\Support\ArtisanLauncher;
use App\Support\ConsumerPoolManager;
use App\Support\RabbitMqManagement;
use App\Support\RabbitMqMetrics;
use Illuminate\Http\Request;

/**
 * Consumer Lab: scales the store-service consumers to N processes, publishes a
 * batch and measures how fast the consumers drain the queue.
 */
class ConsumerLabController extends Controller
{
    public function show(RabbitMqMetrics $metrics)
    {
        return view('consumer-lab', ['history' => $metrics->consumerHistory()]);
    }

    public function start(
        Request $request,
        ConsumerPoolManager $pool,
        RabbitMqManagement $management,
        ArtisanLauncher $launcher,
    ) {
        $data = $request->validate([
            'messages' => ['required', 'integer', 'min:1', 'max:100000'],
            'consumers' => ['required', 'integer', 'min:1', 'max:8'],
        ]);
        $consumers = (int) $data['consumers'];
        $runId = bin2hex(random_bytes(8));
        $storeArtisan = realpath(base_path('../store-service/artisan'));
        $queue = config('mirabel_rabbitmq.consumer_lab_queue');
        $mainQueue = $management->queue($queue, 1);
        $retryQueue = $management->queue($queue . '.retry', 1);
        $errorQueue = $management->queue($queue . '.error', 1);

        if ($mainQueue === null || $retryQueue === null || $errorQueue === null || $mainQueue['acknowledged'] === null) {
            return back()->withErrors(['consumers' => 'Não foi possível ler as métricas das filas no RabbitMQ Management.']);
        }

        if ($mainQueue['messages'] > 0 || $retryQueue['messages'] > 0) {
            return back()->withErrors(['consumers' => 'Esvazie as filas principal e retry antes de iniciar uma medição confiável.']);
        }

        try {
            $pool->reconcile(
                $consumers,
                $storeArtisan,
                fn (): ?int => $management->activeConsumers($queue),
                fn () => $launcher->launch($storeArtisan, ['rabbitmq:consume-store-orders']),
            );
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['consumers' => $exception->getMessage()]);
        }

        $launcher->launch(base_path('artisan'), [
            'rabbitmq:stress',
            (int) $data['messages'],
            '--users=' . $consumers * 100,
            '--ack-baseline=' . $mainQueue['acknowledged'],
            '--error-baseline=' . $errorQueue['messages'],
            '--run-id=' . $runId,
            '--consumers=' . $consumers,
        ]);

        return redirect('/consumer-lab')->with('consumer_run_id', $runId);
    }

    public function status(Request $request, RabbitMqMetrics $metrics, RabbitMqManagement $management)
    {
        $runId = (string) $request->query('run_id', '');
        $publisher = $metrics->stress();
        $isThisRun = $runId !== '' && ($publisher['run_id'] ?? '') === $runId;
        $status = $isThisRun ? ($publisher['status'] ?? 'running') : 'queued';
        $result = [
            'run_id' => $runId,
            'status' => $status,
            'requested' => $isThisRun ? ($publisher['requested'] ?? 0) : 0,
            'published' => $isThisRun ? ($publisher['published'] ?? 0) : 0,
            'requested_consumers' => $isThisRun ? ($publisher['requested_consumers'] ?? 0) : 0,
            'processed' => null,
            'errors' => null,
            'error_backlog' => null,
            'backlog' => null,
            'retry_backlog' => null,
            'unacknowledged' => null,
            'consumers' => null,
            'elapsed_ms' => $isThisRun ? ($publisher['elapsed_ms'] ?? 0) : 0,
            'consumer_elapsed_ms' => 0,
        ];

        if (!$isThisRun) {
            return response()->json($result);
        }

        $queue = config('mirabel_rabbitmq.consumer_lab_queue');
        $mainQueue = $management->queue($queue, 1);
        $retryQueue = $management->queue($queue . '.retry', 1);
        $errorQueue = $management->queue($queue . '.error', 1);

        if ($mainQueue === null || $retryQueue === null || $errorQueue === null || $mainQueue['acknowledged'] === null) {
            $result['status'] = 'monitoring_unavailable';

            return response()->json($result);
        }

        $acknowledged = max(0, $mainQueue['acknowledged'] - (int) ($publisher['ack_baseline'] ?? 0));
        $errors = max(0, $errorQueue['messages'] - (int) ($publisher['error_baseline'] ?? 0));
        $processed = max(0, $acknowledged - $errors);
        $backlog = $mainQueue['messages'];
        $retryBacklog = $retryQueue['messages'];
        $elapsed = max(0, (int) round(microtime(true) * 1000) - (int) ($publisher['started_at_ms'] ?? 0));

        $result = array_merge($result, [
            'measurement' => 'queue_ack_v1',
            'processed' => $processed,
            'errors' => $errors,
            'error_backlog' => $errorQueue['messages'],
            'difference' => max(0, (int) $result['published'] - $processed - $errors),
            'backlog' => $backlog,
            'retry_backlog' => $retryBacklog,
            'unacknowledged' => $mainQueue['messages_unacknowledged'],
            'consumers' => $mainQueue['consumers'],
            'consumer_elapsed_ms' => $elapsed,
        ]);

        $settled = $backlog === 0 && $retryBacklog === 0;
        $timedOut = $elapsed >= 30000;
        if ($status === 'completed') {
            if (!$settled && !$timedOut) {
                $result['status'] = 'draining';
            } else {
                $accounted = $processed + $errors;
                $result['status'] = !$settled || $accounted !== (int) $result['published']
                    ? 'incomplete'
                    : ($errors > 0 ? 'completed_with_errors' : 'completed');
                $result['throughput'] = $elapsed > 0 ? round($processed / ($elapsed / 1000), 2) : 0;
                $result['completed_at'] = now()->toIso8601String();
                $metrics->saveConsumerRun($result);
            }
        }

        return response()->json($result);
    }
}
