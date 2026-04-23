<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

// =============================================================================
class TimezoneSvc {
	// =========================================================================
	public function timezone(float $lat, float $lon): string {
		$url = config('timezonedb.base_url') . '/get-time-zone';

		$response = Http::retry(5, 1000)
		->get($url, [
			'key' =>		config('timezonedb.key'),
			'format' =>		'json',
			'by' =>			'position',
			'lat' =>		$lat,
			'lng' =>		$lon,
		]);

		return $response->json()['zoneName'];
	}
}
