<?php

namespace App\Events;

use Pablicio\MirabelRabbitmq\RabbitMQEventsConnection;

class StoreOrderCreatedEvent
{
  use RabbitMQEventsConnection;

  const ROUTING_KEY = 'store-services.order.created';

  function __construct($payload)
  {
    $this->routingKey = self::ROUTING_KEY;
    $this->payload = $payload;
  }
}
