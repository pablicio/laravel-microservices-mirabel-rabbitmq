<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/** Production Gate: turns the numbers of a load test into a go / pilot / no-go verdict. */
class ProductionReadinessController extends Controller
{
    /** Messages per second one publisher process sustained in the Stress Test on the reference machine. */
    private const PUBLISHER_MSG_PER_SECOND = 7872;

    public function show()
    {
        return view('production-readiness');
    }

    public function evaluate(Request $request)
    {
        $data = $request->validate([
            'messages' => ['required', 'integer', 'min:1000', 'max:1000000'],
            'users' => ['required', 'integer', 'min:1', 'max:100000'],
            'publishers' => ['required', 'integer', 'min:1', 'max:8'],
            'consumers' => ['required', 'integer', 'min:1', 'max:8'],
            'message_rate' => ['required', 'numeric', 'min:1', 'max:50000'],
            'consumer_rate' => ['required', 'numeric', 'min:100', 'max:20000'],
            'p95_ms' => ['required', 'numeric', 'min:1', 'max:10000'],
            'failure_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'confirms' => ['nullable', 'boolean'],
            'failover' => ['nullable', 'boolean'],
        ]);

        $messageRate = (float) $data['message_rate'];
        $publisherCapacity = self::PUBLISHER_MSG_PER_SECOND * (int) $data['publishers'];
        $consumerCapacity = (float) $data['consumer_rate'] * (int) $data['consumers'];
        $checks = [
            $this->check(
                'Consumidores acompanham a entrada',
                $consumerCapacity >= $messageRate ? 'pass' : 'fail',
                $this->number($consumerCapacity) . ' msg/s de consumo contra ' . $this->number($messageRate) . ' msg/s de entrada.',
            ),
            $this->check(
                'Publisher suporta a meta',
                $publisherCapacity >= $messageRate ? 'pass' : 'fail',
                $this->number($publisherCapacity) . ' msg/s estimadas com ' . $data['publishers'] . ' publisher(s).',
            ),
            !empty($data['confirms'])
                ? $this->check('Publisher confirms', 'pass', 'O broker confirma a entrega.')
                : $this->check('Publisher confirms', 'warn', 'Sem confirmação, a entrega não tem a mesma garantia operacional.'),
            $this->check('Latência p95', (float) $data['p95_ms'] <= 250 ? 'pass' : 'warn', 'p95 observado: ' . $data['p95_ms'] . ' ms.'),
            $this->check('Taxa de falha', (float) $data['failure_rate'] <= 1 ? 'pass' : 'fail', 'Falhas estimadas: ' . $data['failure_rate'] . '%.'),
            !empty($data['failover'])
                ? $this->check('Teste de failover', 'pass', 'A recuperação foi exercitada.')
                : $this->check('Teste de failover', 'warn', 'Ainda falta simular queda do broker ou da rede.'),
        ];

        $score = 100;
        foreach ($checks as $check) {
            $score -= match ($check['status']) {
                'fail' => 25,
                'warn' => 10,
                default => 0,
            };
        }
        $hasFailure = collect($checks)->contains('status', 'fail');
        $verdict = $score >= 80 && !$hasFailure ? 'production_candidate' : ($score >= 55 ? 'pilot' : 'not_ready');

        return redirect('/production-readiness')->with('readiness', [
            'score' => max(0, $score),
            'verdict' => $verdict,
            'messages' => (int) $data['messages'],
            'users' => (int) $data['users'],
            'duration_seconds' => (int) ceil((int) $data['messages'] / max(1, min($publisherCapacity, $messageRate))),
            'publisher_capacity' => $publisherCapacity,
            'consumer_capacity' => $consumerCapacity,
            'backlog_per_second' => max(0, $messageRate - $consumerCapacity),
            'checks' => $checks,
        ]);
    }

    private function check(string $name, string $status, string $detail): array
    {
        return ['name' => $name, 'status' => $status, 'detail' => $detail];
    }

    private function number(float $value): string
    {
        return number_format($value, 0, ',', '.');
    }
}
