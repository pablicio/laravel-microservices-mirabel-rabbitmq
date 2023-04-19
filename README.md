# Getting started

## Installation

Clone the repository

    git clone https://github.com/pablicio/laravel-microservices-mirabel-rabbitmq.git

Switch to the repo folder

    cd store-service OR cd order-service

Install all the dependencies using composer

    composer install

Copy the example env file and make the required configuration changes in the .env file

    cp .env.example .env

Generate a new application key

    php artisan key:generate

Start the local development server

    php artisan serve
    
 Start the tinker terminal

    php artisan tinker
