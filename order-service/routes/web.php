<?php

use App\Http\Controllers\ApplicationsTestController;
use App\Http\Controllers\BlackFridayController;
use App\Http\Controllers\ConsumerLabController;
use App\Http\Controllers\MessageLabController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\ProductionReadinessController;
use App\Http\Controllers\StressTestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — the RabbitMQ labs
|--------------------------------------------------------------------------
*/

Route::get('/', [MessageLabController::class, 'index']);
Route::post('/test/generate', [MessageLabController::class, 'generate']);
Route::get('/test/status', [MessageLabController::class, 'status']);

Route::get('/applications-test', [ApplicationsTestController::class, 'show']);
Route::post('/applications-test/simulate', [ApplicationsTestController::class, 'simulate']);

Route::get('/black-friday', [BlackFridayController::class, 'show']);
Route::post('/black-friday/checkout', [BlackFridayController::class, 'checkout']);

Route::get('/stress-test', [StressTestController::class, 'show']);
Route::post('/stress-test/start', [StressTestController::class, 'start'])->middleware('throttle:5,1');
Route::get('/stress-test/status', [StressTestController::class, 'status']);

Route::get('/consumer-lab', [ConsumerLabController::class, 'show']);
Route::post('/consumer-lab/start', [ConsumerLabController::class, 'start']);
Route::get('/consumer-lab/status', [ConsumerLabController::class, 'status']);

Route::get('/production-readiness', [ProductionReadinessController::class, 'show']);
Route::post('/production-readiness/evaluate', [ProductionReadinessController::class, 'evaluate']);

Route::get('/rabbitmq/metrics', MetricsController::class);
