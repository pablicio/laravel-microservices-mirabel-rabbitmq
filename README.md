

# Getting started

## Installation

Clone the repository

    git clone https://github.com/pablicio/laravel-microservices-mirabel-rabbitmq.git

Switch to the repo folder

    cd store-service OR cd order-service

Install all the dependencies using composer

    composer install

The two services use the local library from `C:\projetos\IA\mirabel-rabbitmq`
through a Composer `path` repository with symlinks. After changing the library,
refresh either service with:

    composer update mirabel/rabbitmq

Copy the example env file and make the required configuration changes in the .env file

    cp .env.example .env

The RabbitMQ variables are already present in `.env.example`.

Generate a new application key

    php artisan key:generate

Start the local development server

    php artisan serve
    
 Start the tinker terminal

    php artisan tinker
    
 ![1_a0I73CDAqYGI5fCfwsdOyg](https://user-images.githubusercontent.com/19760320/233184662-e45add33-8107-45f2-908f-2d8bf3a5416a.png)

## Local RabbitMQ flow

Start RabbitMQ from Docker:

    docker run --rm --name mirabel-rabbitmq -p 5672:5672 -p 15672:15672 rabbitmq:4-management

Run one consumer in each service. In `store-service`:

    php artisan tinker
    (new App\Workers\StoreOrderCreatedWorker)->subscribe();

In `order-service`:

    php artisan tinker
    (new App\Workers\StoreOrderReceivedWorker)->subscribe();

Publish from a third terminal in `order-service`:

    php artisan tinker
    (new App\Events\StoreOrderCreatedEvent(['id' => 123]))->publish();

The message is consumed by `store-service`. To publish an
`OrderReceivedEvent` back to `order-service`, use a terminal in
`store-service`:

    cd store-service
    php artisan tinker
    (new App\Events\OrderReceivedEvent(['id' => 123]))->publish();

The local package is installed as `mirabel/rabbitmq`, not the old
`pablicio/mirabel-rabbitmq` package.

