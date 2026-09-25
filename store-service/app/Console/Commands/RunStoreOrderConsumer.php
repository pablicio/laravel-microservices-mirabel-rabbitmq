<?php

namespace App\Console\Commands;

use App\Workers\StoreOrderCreatedWorker;
use Illuminate\Console\Command;

class RunStoreOrderConsumer extends Command
{
    protected $signature = 'rabbitmq:consume-store-orders';

    protected $description = 'Run the real store order RabbitMQ consumer';

    public function handle(): int
    {
        (new StoreOrderCreatedWorker())->subscribe();

        return self::SUCCESS;
    }
}