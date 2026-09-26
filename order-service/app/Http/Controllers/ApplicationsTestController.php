<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Applications Test: simulates, without a broker, what failure and duplicate
 * rates do to retries and to the error queue.
 */
class ApplicationsTestController extends Controller
{
    private const SAMPLE_SIZE = 12;

    public function show()
    {
        return view('applications-test');
    }

    public function simulate(Request $request)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'failure_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'duplicate_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:5'],
        ]);
        $quantity = (int) $data['quantity'];
        $failureRate = (float) $data['failure_rate'];
        $duplicateRate = (float) $data['duplicate_rate'];
        $maxAttempts = (int) $data['max_attempts'];
        $summary = ['processed' => 0, 'retried' => 0, 'duplicates' => 0, 'error_queue' => 0];
        $sample = [];

        for ($index = 1; $index <= $quantity; $index++) {
            $attempts = 0;
            $outcome = 'processed';

            if ($this->happens($duplicateRate)) {
                $summary['duplicates']++;
                $outcome = 'duplicate';
            } else {
                do {
                    $attempts++;
                    $failed = $this->happens($failureRate);
                } while ($failed && $attempts < $maxAttempts);

                $summary['retried'] += max(0, $attempts - 1);
                if ($failed) {
                    $summary['error_queue']++;
                    $outcome = 'error_queue';
                } else {
                    $summary['processed']++;
                }
            }

            if (count($sample) < self::SAMPLE_SIZE) {
                $sample[] = [
                    'order_id' => 'sim-order-' . str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                    'attempts' => $attempts,
                    'outcome' => $outcome,
                ];
            }
        }

        return redirect('/applications-test')->with('simulation', [
            'quantity' => $quantity,
            'failure_rate' => $failureRate,
            'duplicate_rate' => $duplicateRate,
            'max_attempts' => $maxAttempts,
            'summary' => $summary,
            'sample' => $sample,
        ]);
    }

    private function happens(float $percent): bool
    {
        return random_int(1, 10000) <= (int) ($percent * 100);
    }
}
