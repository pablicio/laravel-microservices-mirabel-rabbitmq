<?php

namespace App\Console\Commands;

use App\Workers\StoreOrderCreatedWorker;
use Illuminate\Console\Command;

/** Started (and stopped) by order-service's Consumer Lab, one process per consumer. */
class RunStoreOrderConsumer extends Command
{
    protected $signature = 'rabbitmq:consume-store-orders';

    protected $description = 'Run the real store order RabbitMQ consumer';

    public function handle(): int
    {
        $this->laravel->make(StoreOrderCreatedWorker::class)->subscribe();

        return self::SUCCESS;
    }
}
