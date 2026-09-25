<?php

namespace App\Support;

final class RabbitMqMetrics
{
    private const EVENTS = [
        'published',
        'processed',
        'message_retried',
        'message_sent_to_error_queue',
        'duplicate_message',
        'publish_failed',
    ];

    public function __construct(private readonly string $path = '')
    {
    }

    public function record(string $event): void
    {
        $this->recordMany($event, 1);
    }

    public function recordMany(string $event, int $amount): void
    {
        if ($amount < 1) {
            return;
        }

        $metrics = $this->read();
        $metrics[$event] = ($metrics[$event] ?? 0) + $amount;
        $this->write($metrics);
    }

    public function reset(): void
    {
        $this->write([]);
    }

    public function snapshot(): array
    {
        return $this->read();
    }

    public function batch(): array
    {
        $path = dirname($this->path()) . '/rabbitmq-last-batch.json';
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function saveBatch(array $batch): void
    {
        $path = dirname($this->path()) . '/rabbitmq-last-batch.json';
        file_put_contents($path, json_encode($batch, JSON_THROW_ON_ERROR), LOCK_EX);
    }

    public function stress(): array
    {
        $path = dirname($this->path()) . '/rabbitmq-stress.json';
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function saveStress(array $stress): void
    {
        $path = dirname($this->path()) . '/rabbitmq-stress.json';
        file_put_contents($path, json_encode($stress, JSON_THROW_ON_ERROR), LOCK_EX);

        if (($stress['status'] ?? null) === 'completed') {
            $historyPath = dirname($this->path()) . '/rabbitmq-stress-history.json';
            $history = is_file($historyPath)
                ? json_decode((string) file_get_contents($historyPath), true)
                : [];
            $history = is_array($history) ? $history : [];
            array_unshift($history, $stress);
            file_put_contents($historyPath, json_encode(array_slice($history, 0, 20), JSON_THROW_ON_ERROR), LOCK_EX);
        }
    }

    public function stressHistory(): array
    {
        $path = dirname($this->path()) . '/rabbitmq-stress-history.json';
        if (!is_file($path)) {
            return [];
        }

        $history = json_decode((string) file_get_contents($path), true);

        return is_array($history) ? $history : [];
    }

    public function renderPrometheus(): string
    {
        $lines = [
            '# HELP rabbitmq_messages_total Total number of RabbitMQ telemetry events.',
            '# TYPE rabbitmq_messages_total counter',
        ];

        foreach ($this->read() as $event => $count) {
            $event = str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], $event);
            $lines[] = sprintf('rabbitmq_messages_total{event="%s"} %d', $event, $count);
        }

        return implode("\n", $lines) . "\n";
    }

    private function read(): array
    {
        $path = $this->path();
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? array_intersect_key($decoded, array_flip(self::EVENTS)) : [];
    }

    private function write(array $metrics): void
    {
        $path = $this->path();
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($path, json_encode($metrics, JSON_THROW_ON_ERROR), LOCK_EX);
    }

    private function path(): string
    {
        return $this->path !== '' ? $this->path : storage_path('app/rabbitmq-metrics.json');
    }
}
