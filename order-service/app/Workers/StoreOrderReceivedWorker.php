<?php

namespace App\Workers;

use App\Events\OrderReceivedEvent;
use Illuminate\Support\Facades\Log;
use Mirabel\RabbitMQ\Envelope;
use Mirabel\RabbitMQ\Worker;

/**
 * Receives the store-service's confirmation that an order arrived.
 * The payload "test" always fails, to watch retry → error queue in the UI.
 * The event class lives in store-service, so the key is written out here.
 */
class StoreOrderReceivedWorker extends Worker
{
    const QUEUE = 'store-services.orders.received',
        ROUTING_KEYS = ['order-services.order.received'],
        ATTEMPTS = 4,
        RETRY_DELAY = 3_600;

    public function handle(Envelope $envelope): void
    {
        if ($envelope->body === 'test') {
            throw new \RuntimeException('Simulated failure: the payload "test" always fails.');
        }

        Log::info('Order received by the store.', [
            'body' => $envelope->body,
            'message_id' => $envelope->messageId,
        ]);
    }
}
