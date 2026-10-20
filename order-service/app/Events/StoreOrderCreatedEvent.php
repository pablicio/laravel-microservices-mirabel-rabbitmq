<?php

namespace App\Events;

use Mirabel\RabbitMQ\Event;

class StoreOrderCreatedEvent extends Event
{
  const ROUTING_KEY = 'store-services.order.created';
}
