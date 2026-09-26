<?php

namespace App\Support;

/**
 * mirabel/rabbitmq reads its settings with getenv(). Laravel stops loading
 * .env once `php artisan config:cache` runs, so the values live in
 * config/mirabel_rabbitmq.php and are exported to the process environment at
 * boot. A variable already set by the real environment (Docker, systemd,
 * the shell) always wins.
 */
final class MirabelEnvironment
{
    /** @param array<string, scalar|null> $variables */
    public static function export(array $variables): void
    {
        foreach ($variables as $name => $value) {
            if ($value === null || getenv($name) !== false) {
                continue;
            }

            putenv($name . '=' . (is_bool($value) ? ($value ? 'true' : 'false') : $value));
        }
    }
}
