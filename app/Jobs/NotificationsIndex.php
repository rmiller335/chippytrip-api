<?php

namespace App\Jobs;

use App\Models\Watch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Notifications\DatabaseNotification as Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// =============================================================================
class NotificationsIndex implements ShouldQueue {
    use Queueable;

	protected array $watchCallbacks = [
		\App\Notifications\AirportDelay::class,
		\App\Notifications\ArrivalDelay::class,
		\App\Notifications\Arrival::class,
		\App\Notifications\Cancelled::class,
		\App\Notifications\Change::class,
		\App\Notifications\Delay::class,
		\App\Notifications\Departure::class,
		\App\Notifications\Diverted::class,
		\App\Notifications\Filed::class,
		\App\Notifications\GateChange::class,
		\App\Notifications\In::class,
		\App\Notifications\Offblock::class,
		\App\Notifications\Onblock::class,
		\App\Notifications\Out::class,
	];

	// =========================================================================
    public function __construct() {
    }

	// =========================================================================
    public function handle(): void {
		Log::debug("NotificationsIndex::handle");

		DB::table('notifications')
			->whereIn('type', $this->watchCallbacks)
			->whereNotExists(function ($query) {
				$query->select(DB::raw(1))
					->from('watches_notifications')
					->whereColumn('watches_notifications.notification_id', 'notifications.id');
			})
			->chunkById(200, function ($records) {
				foreach ($records as $rec) {
					$data = json_decode($rec->data);
					Log::debug(json_encode($data, JSON_PRETTY_PRINT));

					if(isset($data->watch_id)) {
						$watch = Watch::find($data->watch_id);
						$notification = Notification::find($rec->id);

						$watch->notifications()->attach($notification->id);
					}
				}
			})
		;
    }
}
