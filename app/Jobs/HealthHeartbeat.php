<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

// =============================================================================
// Dispatched by the health check so the queue always has a job to process;
// if the worker is down, this job sits in the queue and the check fails.
class HealthHeartbeat implements ShouldQueue {
    use Queueable;

	public const CACHE_KEY = 'health:queue_heartbeat';

	// =========================================================================
    public function handle(): void {
		Cache::forever(self::CACHE_KEY, now()->toIso8601String());
    }
}
