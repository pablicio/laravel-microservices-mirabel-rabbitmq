<?php

namespace App\Workers;

use App\Events\OrderReceivedEvent;
use Illuminate\Support\Facades\Log;
use Mirabel\RabbitMQ\Envelope;
use Mirabel\RabbitMQ\Worker;

/**
 * Listens to two routing keys, one of them shared with order-service's
 * StoreOrderReceivedWorker: each queue gets its own copy of the message.
 */
class TestOrderDoneWorker extends Worker
{
    const QUEUE = 'order-services.order-test.done',
        EVENTS = [OrderReceivedEvent::class],
        ROUTING_KEYS = ['test-service.order.done'],
        ATTEMPTS = 8,
        RETRY_DELAY = 1_000;

    public function handle(Envelope $envelope): void
    {
        Log::info('Order test done.', ['body' => $envelope->body, 'type' => $envelope->type]);
    }
}
