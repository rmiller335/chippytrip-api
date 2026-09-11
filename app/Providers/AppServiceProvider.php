<?php

namespace App\Providers;

use App\Services\EmailBodyExtractor;
use App\Services\MailparseEmailBodyExtractor;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;

// =============================================================================
class AppServiceProvider extends ServiceProvider {
	// =========================================================================
	public function register(): void {
		$this->app->singleton(Messaging::class, function () {
			$credentials = config('firebase.credentials');
			$path = str_starts_with($credentials, '/') ? $credentials : base_path($credentials);

			return (new Factory())
				->withServiceAccount($path)
				->createMessaging();
		});

		$this->app->bind(
			EmailBodyExtractor::class,
			MailparseEmailBodyExtractor::class,
		);
	}

	// =========================================================================
	public function boot(): void {
		Relation::enforceMorphMap([
			'airport' =>		\App\Models\Airport::class,
			'flight' =>			\App\Models\Flight::class,
			'inbound_email' =>	\App\Models\InboundEmail::class,
			'listener' =>		\App\Models\Listener::class,
			'user' =>			\App\Models\User::class,
			'watch' =>			\App\Models\Watch::class,
		]);
	}
}
