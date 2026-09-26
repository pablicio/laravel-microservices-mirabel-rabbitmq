<?php

namespace App\Http\Controllers;

use App\Support\RabbitMqMetrics;

/** Prometheus scrape endpoint. */
class MetricsController extends Controller
{
    public function __invoke(RabbitMqMetrics $metrics)
    {
        return response($metrics->renderPrometheus(), 200, [
            'Content-Type' => 'text/plain; version=0.0.4',
        ]);
    }
}
