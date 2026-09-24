<?php

namespace Tests\Safe\Http\Controllers;

use App\Models\Flight;
use App\Models\User;
use App\Models\Watch;
use Illuminate\Support\Facades\Http;
use Tests\Safe\TestCase;

// =============================================================================
// DELETE /flights/{flight} only removes listeners. The watch and flight stay
// until maintenance:nightly finds no one listening (see MaintenanceNightlyTest).
class FlightUnwatchTest extends TestCase {
	private Flight $flight;
	private Watch $watch;

	// =========================================================================
	protected function setUp(): void {
		parent::setUp();

		// Nothing here should reach FlightAware.
		Http::fake();

		$this->flight = $this->makeFlight();
		$this->watch = Watch::create([
			'flight_id' => $this->flight->id,
			'subscription_id' => 'SUB123',
			'secret' => 'topsecret',
			'enabled' => true,
		]);
	}

	// =========================================================================
	protected function tearDown(): void {
		Http::assertNothingSent();

		parent::tearDown();
	}

	// =========================================================================
	private function listen(User $user): void {
		$user->listeners()->create(['watch_id' => $this->watch->id, 'travelers' => '1']);
	}

	// =========================================================================
	private function unwatch(User $user) {
		return $this->actingAs($user, 'sanctum')->deleteJson("/api/flights/{$this->flight->id}");
	}

	// =========================================================================
	public function test_last_listener_is_removed_but_watch_and_flight_stay(): void {
		$user = User::factory()->create();
		$this->listen($user);

		$this->unwatch($user)->assertNoContent();

		$this->assertDatabaseCount('listeners', 0);
		$this->assertModelExists($this->watch);
		$this->assertModelExists($this->flight);
	}

	// =========================================================================
	public function test_other_users_listener_is_untouched(): void {
		$user = User::factory()->create();
		$other = User::factory()->create();
		$this->listen($user);
		$this->listen($other);

		$this->unwatch($user)->assertNoContent();

		$this->assertDatabaseMissing('listeners', ['user_id' => $user->id]);
		$this->assertDatabaseHas('listeners', ['user_id' => $other->id, 'watch_id' => $this->watch->id]);
	}

	// =========================================================================
	public function test_family_member_listeners_are_removed_with_the_user(): void {
		$user = User::factory()->create();
		$member = User::factory()->create();
		$user->family()->attach($member->id, ['name' => 'Kid', 'auto_add' => false]);
		$this->listen($user);
		$this->listen($member);

		$this->unwatch($user)->assertNoContent();

		$this->assertDatabaseCount('listeners', 0);
	}

	// =========================================================================
	public function test_other_users_family_member_is_untouched(): void {
		$user = User::factory()->create();
		$other = User::factory()->create();
		$othersMember = User::factory()->create();
		$other->family()->attach($othersMember->id, ['name' => 'Kid', 'auto_add' => false]);
		$this->listen($user);
		$this->listen($othersMember);

		$this->unwatch($user)->assertNoContent();

		$this->assertDatabaseHas('listeners', ['user_id' => $othersMember->id]);
	}

	// =========================================================================
	public function test_user_not_watching_the_flight_gets_404(): void {
		$other = User::factory()->create();
		$this->listen($other);

		$this->unwatch(User::factory()->create())->assertNotFound();

		$this->assertDatabaseHas('listeners', ['user_id' => $other->id]);
	}

	// =========================================================================
	public function test_unknown_flight_gets_404(): void {
		$this->actingAs(User::factory()->create(), 'sanctum')
			->deleteJson('/api/flights/999999')
			->assertNotFound();
	}

	// =========================================================================
	public function test_requires_authentication(): void {
		$this->deleteJson("/api/flights/{$this->flight->id}")->assertUnauthorized();
	}
}
