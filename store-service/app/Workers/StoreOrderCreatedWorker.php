<?php

namespace App\Workers;

use Pablicio\MirabelRabbitmq\RabbitMQWorkersConnection;

class StoreOrderCreatedWorker
{
  use RabbitMQWorkersConnection;

  const QUEUE = 'store-services.orders.created',
    routing_keys = [
      'store-services.order.created'
    ],
    options = [
      'type' => 'topic'
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
    } catch (\Exception $e) {

      return $this->nack($msg);
    }
  }
}
