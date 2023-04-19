<?php

namespace App\Workers;

use Pablicio\MirabelRabbitmq\RabbitMQWorkersConnection;

class TestOrderDoneWorker
{
  use RabbitMQWorkersConnection;

  const QUEUE = 'order-services.order-test.done',
    routing_keys = [
      'test-service.order.done',
      'order-services.order.received'
    ],
    options = [
      'type' => 'topic',
      'queue_durable' => true,
      'exchange_durable' => true
    ],
    retry_options = [
      'x-message-ttl' => 1000,
      'max-attempts' => 8
    ];

  public function work($msg)
  {
    try {
      print_r($msg->body . "\n");

      return $this->ack($msg);
    } catch (\Exception $e) {

      return $this->nack($msg);
    }
  }
}