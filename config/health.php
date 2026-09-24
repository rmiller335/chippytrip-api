<?php

return [
	// Must be sent as the X-Health-Token header; /health returns 404
	// without it. If empty, /health always returns 404.
	'token' => env('HEALTH_TOKEN'),

	// How long a passing external API check is cached, so frequent polling
	// doesn't run up API usage. Failures are cached for 60 seconds.
	'external_ttl' => (int) env('HEALTH_EXTERNAL_TTL', 300),

	// Timeout for each external API call.
	'http_timeout' => (int) env('HEALTH_HTTP_TIMEOUT', 5),

	// A queued job still waiting after this many seconds means the queue
	// worker isn't running.
	'queue_max_wait' => (int) env('HEALTH_QUEUE_MAX_WAIT', 300),

	// maintenance:nightly runs hourly from cron. Fail if it hasn't finished
	// within this many seconds.
	'maintenance_max_age' => (int) env('HEALTH_MAINTENANCE_MAX_AGE', 7200),

	// Fail if the disk holding storage/ has less free space than this.
	'min_free_disk_mb' => (int) env('HEALTH_MIN_FREE_DISK_MB', 500),
];
