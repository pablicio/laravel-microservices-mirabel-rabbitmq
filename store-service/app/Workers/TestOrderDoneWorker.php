<?php

namespace App\Workers;

use Illuminate\Support\Facades\Log;
use Mirabel\RabbitMQ\Worker;

/**
 * Listens to two routing keys, one of them shared with order-service's
 * StoreOrderReceivedWorker: each queue gets its own copy of the message.
 */
class TestOrderDoneWorker extends Worker
{
    const QUEUE = 'order-services.order-test.done',
        routing_keys = ['test-service.order.done', 'order-services.order.received'],
        options = ['exchange_type' => 'topic'],
        retry_options = ['x-message-ttl' => 1000, 'max-attempts' => 8];

    public function work($msg)
    {
        Log::info('Order test done.', ['body' => $msg->getBody(), 'type' => $msg->get('type')]);

        return $this->ack($msg);
    }
}
