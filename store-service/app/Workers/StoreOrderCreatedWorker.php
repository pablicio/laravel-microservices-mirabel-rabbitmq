<?php

namespace App\Workers;

use Mirabel\RabbitMQ\Worker;

class StoreOrderCreatedWorker extends Worker
{
  const QUEUE = 'store-services.orders.created',
    routing_keys = [
      'store-services.order.created'
    ],
    options = [
      'exchange_type' => 'topic'
    ],
    retry_options = [
      'x-message-ttl' => 3600,
      'max-attempts' => 4
    ];

  public function work($msg)
  {
    try {
      print_r($msg->body);

      return $this->ack($msg);
    } catch (\Throwable $exception) {
      return $this->nack($msg);
    }
  }
}
