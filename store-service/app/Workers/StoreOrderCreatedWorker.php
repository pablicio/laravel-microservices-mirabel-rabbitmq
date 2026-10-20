<?php

namespace App\Workers;

use App\Support\RabbitMqMetrics;
use Mirabel\RabbitMQ\Envelope;
use Mirabel\RabbitMQ\Worker;

/**
 * The consumer the Consumer Lab scales: counts every order it processes.
 * Deliberately keeps the library's NullLogger — this is the throughput hot path.
 */
class StoreOrderCreatedWorker extends Worker
{
    const QUEUE = 'store-services.orders.created',
        ROUTING_KEYS = ['store-services.order.created'],
        ATTEMPTS = 4,
        RETRY_DELAY = 3_600;

    public function handle(Envelope $envelope): void
    {
        try {
            app(RabbitMqMetrics::class)->record('processed');
        } catch (\Throwable $exception) {
            app(RabbitMqMetrics::class)->record('message_retried');

            throw $exception;
        }
    }
}
