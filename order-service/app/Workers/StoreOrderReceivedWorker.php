<?php

namespace App\Workers;

use Illuminate\Support\Facades\Log;
use Mirabel\RabbitMQ\Worker;

/**
 * Receives the store-service's confirmation that an order arrived.
 * The payload "test" always fails, to watch retry → error queue in the UI.
 */
class StoreOrderReceivedWorker extends Worker
{
    const QUEUE = 'store-services.orders.received',
        routing_keys = ['order-services.order.received'],
        options = ['exchange_type' => 'topic'],
        retry_options = ['x-message-ttl' => 3600, 'max-attempts' => 4];

    public function work($msg)
    {
        $body = json_decode($msg->getBody(), true, flags: JSON_THROW_ON_ERROR);
        if ($body === 'test') {
            throw new \RuntimeException('Simulated failure: the payload "test" always fails.');
        }

        Log::info('Order received by the store.', [
            'body' => $body,
            'message_id' => $msg->get('message_id'),
        ]);

        return $this->ack($msg);
    }
}
