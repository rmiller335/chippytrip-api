<?php

namespace Tests\Safe\Jobs;

use App\Jobs\NotificationsIndex;
use App\Models\User;
use App\Models\Watch;
use App\Models\WatchCallback;
use App\Notifications\Departure;
use Illuminate\Support\Facades\DB;
use Tests\Safe\TestCase;

// =============================================================================
class NotificationsIndexTest extends TestCase {
	// =========================================================================
	public function test_handle_attaches_unindexed_notifications_to_their_watch(): void {
		$flight = $this->makeFlight();
		$watch = Watch::create(['flight_id' => $flight->id, 'subscription_id' => 'SUB123']);
		$user = User::factory()->create();

		$callback = WatchCallback::fromApiPayload([
			'alert_id' => 'SUB123',
			'event_code' => 'departure',
			'summary' => 'UA100 departed',
			'flight' => ['fa_flight_id' => 'FA123', 'ident' => 'UA100'],
		]);
		$callback->save();

		// Real 'database' channel write -- no external network call involved.
		$user->notify(new Departure($callback));

		$notificationId = DB::table('notifications')->value('id');
		$this->assertNotNull($notificationId, 'expected the database notification channel to have written a row');

		(new NotificationsIndex())->handle();

		$this->assertDatabaseHas('watches_notifications', [
			'watch_id' => $watch->id,
			'notification_id' => $notificationId,
		]);
	}

	// =========================================================================
	public function test_handle_does_not_reattach_already_indexed_notifications(): void {
		$flight = $this->makeFlight();
		$watch = Watch::create(['flight_id' => $flight->id, 'subscription_id' => 'SUB123']);
		$user = User::factory()->create();

		$callback = WatchCallback::fromApiPayload([
			'alert_id' => 'SUB123',
			'event_code' => 'departure',
			'summary' => 'UA100 departed',
			'flight' => ['fa_flight_id' => 'FA123', 'ident' => 'UA100'],
		]);
		$callback->save();

		$user->notify(new Departure($callback));
		$notificationId = DB::table('notifications')->value('id');

		(new NotificationsIndex())->handle();
		(new NotificationsIndex())->handle();

		$this->assertSame(
			1,
			DB::table('watches_notifications')
				->where('watch_id', $watch->id)
				->where('notification_id', $notificationId)
				->count()
		);
	}
}
