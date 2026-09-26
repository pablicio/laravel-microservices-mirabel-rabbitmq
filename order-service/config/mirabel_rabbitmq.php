<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mirabel RabbitMQ
    |--------------------------------------------------------------------------
    |
    | Exported to the process environment by AppServiceProvider, where
    | mirabel/rabbitmq reads it. Kept here (and not only in .env) so it keeps
    | working after `php artisan config:cache`.
    |
    */

    'environment' => [
        'MB_RABBITMQ_HOST' => env('MB_RABBITMQ_HOST', '127.0.0.1'),
        'MB_RABBITMQ_PORT' => env('MB_RABBITMQ_PORT', 5672),
        'MB_RABBITMQ_USER' => env('MB_RABBITMQ_USER', 'guest'),
        'MB_RABBITMQ_PASSWORD' => env('MB_RABBITMQ_PASSWORD', 'guest'),
        'MB_RABBITMQ_VHOST' => env('MB_RABBITMQ_VHOST', '/'),
        'MB_RABBITMQ_EXCHANGE' => env('MB_RABBITMQ_EXCHANGE', 'my-exchange'),
        'MB_RABBITMQ_EXCHANGE_TYPE' => env('MB_RABBITMQ_EXCHANGE_TYPE', 'topic'),
        'MB_RABBITMQ_PUBLISHER_CONFIRMS' => env('MB_RABBITMQ_PUBLISHER_CONFIRMS', false),
        'MB_RABBITMQ_PUBLISH_RETRIES' => env('MB_RABBITMQ_PUBLISH_RETRIES', 3),
    ],

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
