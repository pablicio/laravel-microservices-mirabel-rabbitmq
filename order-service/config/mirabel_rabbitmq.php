<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Labs
    |--------------------------------------------------------------------------
    |
    | Settings of the labs only. The library's own MB_RABBITMQ_* variables
    | are handled by the bridge mirabel/rabbitmq registers in Laravel: they
    | live in config('mirabel') and survive `php artisan config:cache`.
    |
    */

    // RabbitMQ Management HTTP API, used by the labs to read queue depth and consumers.
    'management' => [
        'url' => env('RABBITMQ_MANAGEMENT_URL', 'http://127.0.0.1:15672'),
        'user' => env('MB_RABBITMQ_USER', 'guest'),
        'password' => env('MB_RABBITMQ_PASSWORD', 'guest'),
        'vhost' => env('MB_RABBITMQ_VHOST', '/'),
    ],

    // The consumer the Consumer Lab scales up and down (runs in store-service).
    'consumer_lab_queue' => 'store-services.orders.created',

];
