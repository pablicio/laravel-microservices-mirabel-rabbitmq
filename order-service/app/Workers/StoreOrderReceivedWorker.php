<?php

namespace App\Workers;

use Pablicio\MirabelRabbitmq\RabbitMQWorkersConnection;

class StoreOrderReceivedWorker
{
  use RabbitMQWorkersConnection;

  const QUEUE = 'store-services.orders.received',
    routing_keys = [
      'order-services.order.received'
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
