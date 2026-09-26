<?php

namespace App\Providers;

use App\Support\MirabelEnvironment;
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
        //
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
