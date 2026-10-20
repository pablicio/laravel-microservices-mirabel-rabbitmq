<?php

namespace App\Events;

use Mirabel\RabbitMQ\Event;

class OrderReceivedEvent extends Event
{
  const ROUTING_KEY = 'order-services.order.received';
}