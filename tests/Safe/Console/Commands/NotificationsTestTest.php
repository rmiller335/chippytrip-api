<?php

namespace Tests\Safe\Console\Commands;

use App\Jobs\NotificationsIndex;
use App\Jobs\SendNotification;
use App\Models\User;
use App\Models\Watch;
use App\Models\WatchCallback;
use App\Notifications\Test;
use App\Services\NotificationAuditSvc;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\Safe\TestCase;

// =============================================================================
class NotificationsTestTest extends TestCase {
	// =========================================================================
	private function makeWatch(): Watch {
		$flight = $this->makeFlight();

		return Watch::create(['flight_id' => $flight->id, 'subscription_id' => '1234567']);
	}

	// =========================================================================
	private function listen(Watch $watch): User {
		$user = User::factory()->create();
		$watch->listeners()->create(['user_id' => $user->id, 'travelers' => $user->name]);

		return $user;
	}

	// =========================================================================
	public function test_saves_a_test_callback_that_matches_the_flight_date(): void {
		Bus::fake();
		$watch = $this->makeWatch();
		$this->listen($watch);

		$this->artisan('notifications:test', ['watch' => $watch->id])->assertSuccessful();

		$callback = WatchCallback::sole();
		$this->assertSame('test', $callback->event_code);
		$this->assertSame($watch->id, $callback->watch_id);
		$this->assertTrue($callback->matchesFlightDate($watch->flight));
		$this->assertSame('Test notification for UA100', $callback->title);
		$this->assertInstanceOf(Test::class, $callback->notification());
	}

	// =========================================================================
	public function test_queues_one_chain_per_listener(): void {
		Bus::fake();
		$watch = $this->makeWatch();
		$this->listen($watch);
		$this->listen($watch);

		$this->artisan('notifications:test', ['watch' => $watch->id])->assertSuccessful();

		Bus::assertChained([SendNotification::class, NotificationsIndex::class]);
		Bus::assertDispatchedTimes(SendNotification::class, 2);
	}

	// =========================================================================
	public function test_user_option_limits_to_one_listener(): void {
		Bus::fake();
		$watch = $this->makeWatch();
		$this->listen($watch);
		$user = $this->listen($watch);

		$this->artisan('notifications:test', ['watch' => $watch->id, '--user' => $user->id])
			->assertSuccessful();

		Bus::assertDispatchedTimes(SendNotification::class, 1);
		Bus::assertDispatched(SendNotification::class, fn ($job) => $job->user->is($user));
	}

	// =========================================================================
	public function test_fails_without_listeners(): void {
		Bus::fake();
		$watch = $this->makeWatch();

		$this->artisan('notifications:test', ['watch' => $watch->id])->assertFailed();

		$this->assertSame(0, WatchCallback::count());
		Bus::assertNothingDispatched();
	}

	// =========================================================================
	public function test_notification_is_indexed_to_its_watch(): void {
		$watch = $this->makeWatch();
		$this->listen($watch);

		// Queue is sync in tests, so the chain runs inline.
		$this->artisan('notifications:test', ['watch' => $watch->id])->assertSuccessful();

		$notification = DB::table('notifications')->sole();
		$this->assertSame(Test::class, $notification->type);
		$this->assertDatabaseHas('watches_notifications', [
			'watch_id' =>			$watch->id,
			'notification_id' =>	$notification->id,
		]);
	}

	// =========================================================================
	public function test_audit_ignores_test_callbacks(): void {
		Bus::fake();
		$watch = $this->makeWatch();
		$this->listen($watch);

		$this->artisan('notifications:test', ['watch' => $watch->id])->assertSuccessful();

		$findings = collect(app(NotificationAuditSvc::class)->run())
			->flatMap(fn ($r) => $r['findings'])
			->pluck('code');

		$this->assertNotContains('unknown_event', $findings);
	}
}
