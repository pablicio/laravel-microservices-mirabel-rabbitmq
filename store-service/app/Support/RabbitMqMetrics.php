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
        $metrics = $this->read();
        $metrics[$event] = ($metrics[$event] ?? 0) + 1;
        $this->write($metrics);
    }

    public function reset(): void
    {
        $this->write([]);
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
