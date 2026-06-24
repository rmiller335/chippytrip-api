<?php

namespace Tests\Safe\Services;

use App\Services\TimezoneSvc;
use Illuminate\Support\Facades\Http;
use Tests\Safe\TestCase;

// =============================================================================
class TimezoneSvcTest extends TestCase {
	// =========================================================================
	public function test_timezone_returns_zone_name_from_response(): void {
		Http::fake([
			'*/get-time-zone*' => Http::response(['zoneName' => 'America/Los_Angeles'], 200),
		]);

		$tz = (new TimezoneSvc())->timezone(37.619, -122.379);

		$this->assertSame('America/Los_Angeles', $tz);

		Http::assertSent(fn ($request) => $request['lat'] === 37.619 && $request['lng'] === -122.379);
	}
}
