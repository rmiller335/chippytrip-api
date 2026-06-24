<?php

namespace Tests\Safe\Jobs;

use App\Jobs\EnableWatch;
use App\Models\Watch;
use App\Services\FlightAwareSvc;
use Illuminate\Support\Facades\Http;
use Tests\Safe\TestCase;

// =============================================================================
class EnableWatchTest extends TestCase {
	// =========================================================================
	public function test_handle_enables_watch_on_successful_subscription(): void {
		$flight = $this->makeFlight();
		$watch = Watch::create(['flight_id' => $flight->id]);

		Http::fake([
			'*/alerts' => Http::response(null, 201, ['Location' => '/alerts/SUB123']),
		]);

		(new EnableWatch($watch))->handle(app(FlightAwareSvc::class));

		$watch->refresh();
		$this->assertTrue($watch->enabled);
		$this->assertSame('SUB123', $watch->subscription_id);
		$this->assertNotNull($watch->secret);
	}

	// =========================================================================
	public function test_handle_throws_when_flightaware_rejects_the_subscription(): void {
		$flight = $this->makeFlight();
		$watch = Watch::create(['flight_id' => $flight->id]);

		Http::fake([
			'*/alerts' => Http::response(null, 500),
		]);

		// FlightAwareSvc::watchCreate() aborts(500) on failure rather than
		// returning falsy, so EnableWatch's own Log::error()/fail() branch
		// is currently unreachable -- the exception propagates instead.
		$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

		(new EnableWatch($watch))->handle(app(FlightAwareSvc::class));
	}
}
