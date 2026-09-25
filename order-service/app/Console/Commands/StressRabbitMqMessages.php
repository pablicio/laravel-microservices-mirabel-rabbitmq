<?php

namespace App\Console\Commands;

use App\Events\StoreOrderCreatedEvent;
use App\Support\RabbitMqMetrics;
use Illuminate\Console\Command;
use Mirabel\RabbitMQ\Publishing\PublisherRuntime;

class StressRabbitMqMessages extends Command
{
    protected $signature = 'rabbitmq:stress
        {quantity : Number of real messages to publish}
        {--users=100 : Number of simulated users distributed across messages}
        {--run-id= : Stress run identifier}';

    protected $description = 'Publish a high-volume RabbitMQ stress test and measure limits';

    public function handle(RabbitMqMetrics $metrics): int
    {
        putenv('MB_RABBITMQ_REUSE_CONNECTION=true');
        $quantity = max(1, (int) $this->argument('quantity'));
        $users = max(1, (int) $this->option('users'));
        $runId = (string) ($this->option('run-id') ?: bin2hex(random_bytes(8)));
        $startedAt = microtime(true);
        $published = 0;
        $failed = 0;
        $recordedPublished = 0;
        $recordedFailed = 0;

        $save = function (string $status) use ($metrics, $runId, $quantity, $users, &$published, &$failed, &$recordedPublished, &$recordedFailed, $startedAt): void {
            $metrics->recordMany('published', $published - $recordedPublished);
            $metrics->recordMany('publish_failed', $failed - $recordedFailed);
            $recordedPublished = $published;
            $recordedFailed = $failed;
            $elapsed = microtime(true) - $startedAt;
            $failureRate = $quantity > 0 ? $failed / $quantity : 0;
            $memoryPeakMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
            $latencyMs = $published > 0 ? round(($elapsed * 1000) / $published, 2) : 0;
            $health = $failed === 0 && $elapsed < 30 ? 'healthy' : ($failureRate <= 0.01 ? 'degraded' : 'critical');
            $healthSummary = match ($health) {
                'healthy' => 'Carga concluída sem falhas e dentro da janela de 30 segundos.',
                'degraded' => 'A carga terminou, mas apresentou lentidão ou falhas pontuais.',
                default => 'A carga apresentou falhas relevantes; o limite operacional foi atingido.',
            };
            $recommendation = $health === 'healthy'
                ? 'Aumente gradualmente o volume e observe o throughput antes de usar em produção.'
                : 'Reduza o lote, investigue conexão/broker e repita com o mesmo volume para confirmar o limite.';
            $metrics->saveStress([
                'run_id' => $runId,
                'status' => $status,
                'requested' => $quantity,
                'users' => $users,
                'published' => $published,
                'failed' => $failed,
                'elapsed_ms' => (int) round($elapsed * 1000),
                'throughput' => $elapsed > 0 ? round($published / $elapsed, 2) : 0,
                'messages_per_user' => round($quantity / $users, 2),
                'failure_rate' => round($failureRate * 100, 2),
                'memory_peak_mb' => $memoryPeakMb,
                'latency_ms' => $latencyMs,
                'health' => $health,
                'health_summary' => $healthSummary,
                'recommendation' => $recommendation,
                'limit' => $failed > 0 ? 'connection_or_broker_errors' : ($elapsed > 30 ? 'publisher_throughput' : 'not_reached'),
                'completed_at' => $status === 'completed' ? now()->toIso8601String() : null,
            ]);
        };

        $save('running');
        for ($index = 1; $index <= $quantity; $index++) {
            try {
                (new StoreOrderCreatedEvent([
                    'order_id' => 'stress-' . bin2hex(random_bytes(6)),
                    'sequence' => $index,
                    'run_id' => $runId,
                    'user_id' => 'user-' . (($index - 1) % $users + 1),
                ]))->publish(
                    messageId: 'stress-' . bin2hex(random_bytes(12)),
                    idempotencyKey: 'stress-' . bin2hex(random_bytes(12)),
                );
                $published++;
            } catch (\Throwable $exception) {
                $failed++;
            }

            if ($index % 1000 === 0) {
                $save('running');
            }
        }

        $save('completed');
        PublisherRuntime::closeAll();
        putenv('MB_RABBITMQ_REUSE_CONNECTION');

        return self::SUCCESS;
    }
}