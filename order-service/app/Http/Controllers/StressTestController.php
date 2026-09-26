<?php

namespace App\Http\Controllers;

use App\Support\ArtisanLauncher;
use App\Support\RabbitMqMetrics;
use Illuminate\Http\Request;

/** Stress Test: publishes up to a million real messages and measures the publisher. */
class StressTestController extends Controller
{
    public function show(RabbitMqMetrics $metrics)
    {
        return view('stress-test', [
            'stress' => $metrics->stress(),
            'history' => $metrics->stressHistory(),
        ]);
    }

    public function start(Request $request, RabbitMqMetrics $metrics, ArtisanLauncher $launcher)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'users' => ['required', 'integer', 'min:1', 'max:5000'],
        ]);
        if (($metrics->stress()['status'] ?? null) === 'running') {
            return redirect('/stress-test')->withErrors(['quantity' => 'Já existe um stress test em execução. Aguarde a finalização.']);
        }

        $runId = bin2hex(random_bytes(8));
        $launcher->launch(base_path('artisan'), [
            'rabbitmq:stress',
            (int) $data['quantity'],
            '--users=' . (int) $data['users'],
            '--run-id=' . $runId,
        ]);

        return redirect('/stress-test')->with('stress_run_id', $runId);
    }

    public function status(Request $request, RabbitMqMetrics $metrics)
    {
        $stress = $metrics->stress();
        $runId = (string) $request->query('run_id', '');
        $empty = ['status' => 'idle', 'requested' => 0, 'users' => 0, 'published' => 0, 'failed' => 0];

        if ($runId !== '' && ($stress['run_id'] ?? '') !== $runId) {
            return response()->json(['run_id' => $runId, 'status' => 'queued'] + $empty);
        }

        return response()->json($stress + $empty);
    }
}
