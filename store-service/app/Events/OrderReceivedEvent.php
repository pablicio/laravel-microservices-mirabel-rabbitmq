<?php

namespace App\Events;

use Mirabel\RabbitMQ\Event;

class OrderReceivedEvent extends Event
{
  public static string $routingKey = 'order-services.order.received';
}