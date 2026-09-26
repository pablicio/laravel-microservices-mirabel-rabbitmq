<?php

namespace App\Http\Controllers;

use App\Support\ArtisanLauncher;
use App\Support\RabbitMqMetrics;
use Illuminate\Http\Request;

/** Message Lab: publishes a small batch of real messages and follows it. */
class MessageLabController extends Controller
{
    public function index(RabbitMqMetrics $metrics)
    {
        return view('load-console', [
            'metrics' => $metrics->snapshot(),
            'batch' => $metrics->batch(),
        ]);
    }

    public function generate(Request $request, ArtisanLauncher $launcher)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:500'],
            'delay_ms' => ['nullable', 'integer', 'min:0', 'max:5000'],
        ]);
        $quantity = (int) $data['quantity'];
        $runId = bin2hex(random_bytes(8));

        $launcher->launch(base_path('artisan'), [
            'rabbitmq:generate',
            $quantity,
            '--delay-ms=' . (int) ($data['delay_ms'] ?? 0),
            '--run-id=' . $runId,
        ]);

        return redirect('/')->with('generation', [
            'requested' => $quantity,
            'run_id' => $runId,
            'queued' => true,
        ]);
    }

    public function status(Request $request, RabbitMqMetrics $metrics)
    {
        $batch = $metrics->batch();
        $runId = (string) $request->query('run_id', '');
        $empty = [
            'status' => 'idle',
            'requested' => 0,
            'published' => 0,
            'failed' => 0,
            'samples' => [],
            'metrics' => $metrics->snapshot(),
        ];

        if ($runId !== '' && ($batch['run_id'] ?? '') !== $runId) {
            return response()->json(['run_id' => $runId, 'status' => 'queued'] + $empty);
        }

        return response()->json($batch + $empty);
    }
}
