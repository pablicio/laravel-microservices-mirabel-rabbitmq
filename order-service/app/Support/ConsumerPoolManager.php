<?php

namespace App\Support;

use RuntimeException;

class ConsumerPoolManager
{
    public function reconcile(int $desired, string $artisanPath, callable $getActiveConsumers, callable $startWorker): int
    {
        if ($desired < 1 || $desired > 8) {
            throw new RuntimeException('O pool de consumers deve ficar entre 1 e 8.');
        }

        $lock = fopen(storage_path('framework/consumer-lab-pool.lock'), 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }

            throw new RuntimeException('Outro teste está ajustando o pool de consumers. Tente novamente.');
        }

        try {
            $active = $getActiveConsumers();
            if (!is_int($active) || $active < 0) {
                throw new RuntimeException('Não foi possível consultar os consumers ativos no RabbitMQ.');
            }

            if ($active > $desired) {
                $pids = $this->workerProcessIds($artisanPath);
                $excess = $active - $desired;
                if (count($pids) < $excess) {
                    throw new RuntimeException('Não foi possível identificar com segurança os workers excedentes.');
                }

                foreach (array_slice($pids, -$excess) as $pid) {
                    if (!$this->stopWorker($pid)) {
                        throw new RuntimeException('Falha ao encerrar um worker excedente do Consumer Lab.');
                    }
                }
            } elseif ($active < $desired) {
                for ($index = $active; $index < $desired; $index++) {
                    $startWorker();
                }
            }

            $deadline = microtime(true) + 8;
            do {
                $active = $getActiveConsumers();
                if (!is_int($active) || $active < 0) {
                    throw new RuntimeException('Não foi possível confirmar o pool no RabbitMQ.');
                }
                if ($active === $desired) {
                    return $active;
                }
                usleep(200000);
            } while (microtime(true) < $deadline);

            throw new RuntimeException("O RabbitMQ não confirmou exatamente {$desired} consumers ativos; publisher não iniciado.");
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    protected function workerProcessIds(string $artisanPath): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $output = [];
            exec('ps -eo pid=,args=', $output, $exitCode);
            if ($exitCode !== 0) {
                throw new RuntimeException('Não foi possível listar os processos workers.');
            }

            $artisanPath = realpath($artisanPath) ?: $artisanPath;
            $pids = [];
            foreach ($output as $line) {
                if (str_contains($line, $artisanPath) && str_contains($line, 'rabbitmq:consume-store-orders')) {
                    $pids[] = (int) strtok(trim($line), ' ');
                }
            }

            return array_values(array_filter($pids, static fn (int $pid): bool => $pid > 0));
        }

        $artisanPath = str_replace("'", "''", realpath($artisanPath) ?: $artisanPath);
        $script = "\$artisan = '{$artisanPath}'; Get-CimInstance Win32_Process | "
            . "Where-Object { \$_.Name -like 'php*.exe' -and \$_.CommandLine -and "
            . "\$_.CommandLine.Contains(\$artisan) -and \$_.CommandLine.Contains('rabbitmq:consume-store-orders') } | "
            . 'Sort-Object CreationDate | Select-Object -ExpandProperty ProcessId';
        $encodedScript = iconv('UTF-8', 'UTF-16LE', $script);
        if ($encodedScript === false) {
            throw new RuntimeException('Não foi possível preparar a consulta de workers do Windows.');
        }

        $output = [];
        exec('powershell.exe -NoProfile -NonInteractive -EncodedCommand ' . base64_encode($encodedScript), $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Não foi possível listar os workers do Consumer Lab.');
        }

        return array_values(array_filter(array_map('intval', $output), static fn (int $pid): bool => $pid > 0));
    }

    protected function stopWorker(int $pid): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            exec(sprintf('taskkill /PID %d /T /F', $pid), $output, $exitCode);

            return $exitCode === 0;
        }

        if (function_exists('posix_kill')) {
            return posix_kill($pid, SIGTERM);
        }

        exec(sprintf('kill %d', $pid), $output, $exitCode);

        return $exitCode === 0;
    }
}