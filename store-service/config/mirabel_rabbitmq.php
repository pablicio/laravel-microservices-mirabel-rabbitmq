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

];
