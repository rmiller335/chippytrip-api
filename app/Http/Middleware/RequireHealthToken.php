<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// =============================================================================
// The /health endpoints need the X-Health-Token header. Without it they
// return 404 rather than 401 so they don't advertise themselves.
class RequireHealthToken {
	// =========================================================================
	public function handle(Request $request, Closure $next): Response {
		$token = config('health.token');

		abort_unless(
			! empty($token) && hash_equals($token, (string) $request->header('X-Health-Token')),
			404
		);

		return $next($request);
	}
}
