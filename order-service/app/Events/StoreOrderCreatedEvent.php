<?php

namespace App\Events;

use Mirabel\RabbitMQ\Event;

class StoreOrderCreatedEvent extends Event
{
  public static string $routingKey = 'store-services.order.created';
}
