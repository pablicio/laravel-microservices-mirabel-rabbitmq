<?php

namespace App\Console\Commands;

use App\Events\StoreOrderCreatedEvent;
use App\Support\RabbitMqMetrics;
use Illuminate\Console\Command;

class GenerateRabbitMqMessages extends Command
{
    protected $signature = 'rabbitmq:generate
        {quantity : Number of messages to publish}
        {--delay-ms=0 : Delay between messages}
        {--run-id= : Batch identifier}';

    protected $description = 'Publish a controlled batch of real RabbitMQ order events';

    public function handle(RabbitMqMetrics $metrics): int
    {
        $quantity = max(1, (int) $this->argument('quantity'));
        $delayMs = max(0, (int) $this->option('delay-ms'));
        $runId = (string) ($this->option('run-id') ?: bin2hex(random_bytes(8)));
        $published = 0;
        $failed = 0;
        $samples = [];

        $metrics->saveBatch([
            'run_id' => $runId,
            'status' => 'running',
            'requested' => $quantity,
            'published' => 0,
            'failed' => 0,
            'samples' => [],
        ]);

        for ($index = 1; $index <= $quantity; $index++) {
            $payload = [
                'order_id' => 'test-' . bin2hex(random_bytes(6)),
                'sequence' => $index,
                'generated_at' => now()->toIso8601String(),
            ];
            $messageId = 'test-' . bin2hex(random_bytes(12));
            $idempotencyKey = 'load-' . bin2hex(random_bytes(12));

            if (count($samples) < 5) {
                $samples[] = [
                    'message_id' => $messageId,
                    'idempotency_key' => $idempotencyKey,
                    'payload' => $payload,
                ];
            }

            try {
                (new StoreOrderCreatedEvent($payload))->publish(
                    messageId: $messageId,
                    idempotencyKey: $idempotencyKey,
                );
                $metrics->record('published');
                $published++;
            } catch (\Throwable $exception) {
                $metrics->record('publish_failed');
                $failed++;
            }

            $metrics->saveBatch([
                'run_id' => $runId,
                'status' => 'running',
                'requested' => $quantity,
                'published' => $published,
                'failed' => $failed,
                'samples' => $samples,
            ]);

            if ($delayMs > 0 && $index < $quantity) {
                usleep($delayMs * 1000);
            }
        }

        $metrics->saveBatch([
            'run_id' => $runId,
            'status' => 'completed',
            'requested' => $quantity,
            'published' => $published,
            'failed' => $failed,
            'samples' => $samples,
        ]);

        return self::SUCCESS;
    }
}