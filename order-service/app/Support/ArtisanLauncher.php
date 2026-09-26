<?php

namespace App\Support;

/**
 * Starts an artisan command in the background and returns immediately, so a
 * web request can kick off a long run (stress test, consumer) without waiting.
 */
class ArtisanLauncher
{
    /**
     * @param list<string|int> $arguments command name followed by its arguments/options
     */
    public function launch(string $artisanPath, array $arguments): void
    {
        $parts = array_map(
            static fn (string|int $part): string => escapeshellarg((string) $part),
            [PHP_BINARY, $artisanPath, ...$arguments],
        );
        $command = implode(' ', $parts);

        if (PHP_OS_FAMILY === 'Windows') {
            $command = 'start /B "" ' . $command . ' > NUL 2>&1';
        } else {
            $command .= ' > /dev/null 2>&1 &';
        }

        pclose(popen($command, 'r'));
    }
}
