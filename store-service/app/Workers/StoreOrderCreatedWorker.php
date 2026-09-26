<?php

namespace App\Workers;

use App\Support\RabbitMqMetrics;
use Mirabel\RabbitMQ\Worker;

/**
 * The consumer the Consumer Lab scales: counts every order it processes.
 * Deliberately keeps the library's NullLogger — this is the throughput hot path.
 */
class StoreOrderCreatedWorker extends Worker
{
    const QUEUE = 'store-services.orders.created',
        routing_keys = ['store-services.order.created'],
        options = ['exchange_type' => 'topic'],
        retry_options = ['x-message-ttl' => 3600, 'max-attempts' => 4];

    public function work($msg)
    {
        try {
            app(RabbitMqMetrics::class)->record('processed');

            return $this->ack($msg);
        } catch (\Throwable $exception) {
            app(RabbitMqMetrics::class)->record('message_retried');

            return $this->nack($msg);
        }
    }
}
