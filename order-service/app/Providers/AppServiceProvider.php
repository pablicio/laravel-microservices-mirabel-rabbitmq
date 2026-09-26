<?php

namespace App\Providers;

use App\Support\ArtisanLauncher;
use App\Support\MirabelEnvironment;
use App\Support\RabbitMqManagement;
use App\Support\StoreServiceClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(ArtisanLauncher::class);

        $this->app->singleton(RabbitMqManagement::class, fn ($app) => new RabbitMqManagement(
            $app['config']['mirabel_rabbitmq.management.url'],
            $app['config']['mirabel_rabbitmq.management.user'],
            $app['config']['mirabel_rabbitmq.management.password'],
            $app['config']['mirabel_rabbitmq.management.vhost'],
        ));

        $this->app->singleton(StoreServiceClient::class, fn ($app) => new StoreServiceClient(
            $app['config']['services.store.url'],
        ));
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        MirabelEnvironment::export(config('mirabel_rabbitmq.environment', []));
    }
}
