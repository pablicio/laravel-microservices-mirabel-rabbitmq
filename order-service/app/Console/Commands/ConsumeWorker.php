<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Mirabel\RabbitMQ\Worker;

/**
 * Runs any worker in App\Workers until SIGTERM/SIGINT:
 *
 *   php artisan rabbitmq:consume StoreOrderCreatedWorker
 */
class ConsumeWorker extends Command
{
    protected $signature = 'rabbitmq:consume {worker : Class name inside App\Workers}';

    protected $description = 'Run a Mirabel RabbitMQ worker in the foreground';

    public function handle(): int
    {
        $class = 'App\\Workers\\' . $this->argument('worker');
        if (!class_exists($class) || !is_subclass_of($class, Worker::class)) {
            $this->error("{$class} is not a worker. Available: " . implode(', ', $this->available()));

            return self::INVALID;
        }

        $this->info("Consuming with {$class}. Ctrl+C to stop.");
        $this->laravel->make($class)->subscribe();

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function available(): array
    {
        return array_map(
            static fn (string $file): string => basename($file, '.php'),
            glob(app_path('Workers/*.php')) ?: [],
        );
    }
}
