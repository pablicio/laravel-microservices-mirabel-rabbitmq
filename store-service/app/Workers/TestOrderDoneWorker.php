<?php

namespace App\Workers;

use Mirabel\RabbitMQ\Worker;

class TestOrderDoneWorker extends Worker
{
  const QUEUE = 'order-services.order-test.done',
    routing_keys = [
      'test-service.order.done',
      'order-services.order.received'
    ],
    options = [
      'exchange_type' => 'topic',
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
    } catch (\Throwable $exception) {
      return $this->nack($msg);
    }
  }
}