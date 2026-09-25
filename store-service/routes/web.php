<?php

use App\Support\RabbitMqMetrics;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/rabbitmq/metrics', function (RabbitMqMetrics $metrics) {
    return response($metrics->renderPrometheus(), 200, [
        'Content-Type' => 'text/plain; version=0.0.4',
    ]);
});
