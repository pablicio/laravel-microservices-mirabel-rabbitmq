<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Reads the store-service's Prometheus endpoint to know how many messages its
 * consumers have processed so far.
 */
class StoreServiceClient
{
    public function __construct(private readonly string $url)
    {
    }

    /** Total processed messages, or 0 when the store-service is not running. */
    public function processedCount(): int
    {
        try {
            $body = Http::timeout(1)->get(rtrim($this->url, '/') . '/rabbitmq/metrics')->body();
        } catch (\Throwable) {
            return 0;
        }

        if (preg_match('/rabbitmq_messages_total\{event="processed"\}\s+(\d+)/', $body, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }
}
