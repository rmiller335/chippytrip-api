<?php

namespace Tests\Safe\Models;

use App\Models\Watch;
use Carbon\Carbon;
use Tests\Safe\TestCase;

// =============================================================================
class WatchTest extends TestCase {
	// =========================================================================
	public function test_watchable_is_true_when_flight_departs_today(): void {
		$flight = $this->makeFlight(['departure_date' => Carbon::now()->toDateString()]);
		$watch = Watch::create(['flight_id' => $flight->id]);

		$this->assertTrue($watch->watchable());
	}

	// =========================================================================
	public function test_watchable_is_false_when_flight_departs_far_in_the_future(): void {
		$flight = $this->makeFlight(['departure_date' => Carbon::now()->addDays(30)->toDateString()]);
		$watch = Watch::create(['flight_id' => $flight->id]);

		$this->assertFalse($watch->watchable());
	}

	// =========================================================================
	public function test_enable_sets_subscription_secret_and_enabled(): void {
		$flight = $this->makeFlight();
		$watch = Watch::create(['flight_id' => $flight->id]);

		$watch->enable('SUB123', 'secret123');

		$this->assertSame('SUB123', $watch->subscription_id);
		$this->assertSame('secret123', $watch->secret);
		$this->assertTrue($watch->enabled);
	}

	// =========================================================================
	public function test_disable_clears_subscription_and_secret(): void {
		$flight = $this->makeFlight();
		$watch = Watch::create([
			'flight_id' => $flight->id,
			'subscription_id' => 'SUB123',
			'secret' => 'secret123',
			'enabled' => true,
		]);

		$watch->disable();

		$this->assertNull($watch->subscription_id);
		$this->assertNull($watch->secret);
		$this->assertFalse($watch->enabled);
	}

	// =========================================================================
	public function test_gen_secret_returns_32_char_hex_string(): void {
		$secret = Watch::genSecret();

		$this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $secret);
	}

	// =========================================================================
	public function test_flight_relation(): void {
		$flight = $this->makeFlight();
		$watch = Watch::create(['flight_id' => $flight->id]);

		$this->assertTrue($watch->flight->is($flight));
	}
}
