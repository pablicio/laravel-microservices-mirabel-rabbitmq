<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Thin client for the RabbitMQ Management HTTP API (the same data the
 * management UI on :15672 shows). Returns null when the API is unreachable.
 */
class RabbitMqManagement
{
    public function __construct(
        private readonly string $url,
        private readonly string $user,
        private readonly string $password,
        private readonly string $vhost = '/',
    ) {
    }

    /** @return array{messages: int, messages_ready: int, messages_unacknowledged: int, consumers: int, acknowledged: ?int}|null */
    public function queue(string $name, int $timeoutSeconds = 2): ?array
    {
        try {
            $response = Http::withBasicAuth($this->user, $this->password)
                ->timeout($timeoutSeconds)
                ->get(sprintf('%s/api/queues/%s/%s', rtrim($this->url, '/'), rawurlencode($this->vhost), rawurlencode($name)));
        } catch (\Throwable) {
            return null;
        }

        if (!$response->successful() || !is_numeric($response->json('consumers'))) {
            return null;
        }

        $acknowledged = $response->json('message_stats.ack');

        return [
            'messages' => (int) $response->json('messages', 0),
            'messages_ready' => (int) $response->json('messages_ready', 0),
            'messages_unacknowledged' => (int) $response->json('messages_unacknowledged', 0),
            'consumers' => (int) $response->json('consumers'),
            'acknowledged' => is_numeric($acknowledged) ? (int) $acknowledged : null,
        ];
    }

    public function activeConsumers(string $queue): ?int
    {
        return $this->queue($queue)['consumers'] ?? null;
    }
}
