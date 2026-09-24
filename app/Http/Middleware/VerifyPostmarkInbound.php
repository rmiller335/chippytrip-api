<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

// =============================================================================
// Only Postmark knows the basic-auth credentials in its inbound webhook
// URL, so this keeps anyone else from posting emails. See config/postmark.php.
class VerifyPostmarkInbound {
	// =========================================================================
	public function handle(Request $request, Closure $next): Response {
		$user = (string) config('postmark.inbound_user');
		$password = (string) config('postmark.inbound_password');

		$valid = '' !== $password
			&& hash_equals($user, (string) $request->getUser())
			&& hash_equals($password, (string) $request->getPassword());

		if (! $valid) {
			Log::warning('VerifyPostmarkInbound: rejected request', ['ip' => $request->ip()]);

			return response()->json(['message' => 'Unauthenticated.'], 401, [
				'WWW-Authenticate' => 'Basic realm="postmark-inbound"',
			]);
		}

		return $next($request);
	}
}
