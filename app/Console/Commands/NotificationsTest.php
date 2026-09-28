<?php

namespace App\Console\Commands;

use App\Jobs\NotificationsIndex;
use App\Jobs\SendNotification;
use App\Models\Watch;
use App\Models\WatchCallback;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

// =============================================================================
// Sends a 'test' notification through the same path as a FlightAware
// callback: a saved WatchCallback, SendNotification to each listener, then
// NotificationsIndex. It shows up in the app's sync like any other event.
class NotificationsTest extends Command {
	protected $signature = 'notifications:test
		{watch : Watch ID}
		{--user= : Only notify this user ID}';
	protected $description = 'Send a test notification to a watch\'s listeners';

	// =========================================================================
	public function handle(): int {
		$watch = Watch::with('flight.origin', 'flight.destination', 'listeners.user')
			->find($this->argument('watch'));

		if (null === $watch || null === $watch->flight) {
			$this->error('No watch with that ID, or it has no flight.');
			return self::FAILURE;
		}

		$listeners = $watch->listeners
			->when(null !== $this->option('user'),
				fn ($l) => $l->where('user_id', (int) $this->option('user')))
			->filter(fn ($l) => null !== $l->user);

		if ($listeners->isEmpty()) {
			$this->error('No listeners to notify.');
			return self::FAILURE;
		}

		$callback = $this->makeCallback($watch);

		foreach ($listeners as $listener) {
			Bus::chain([
				new SendNotification($callback, $listener->user),
				new NotificationsIndex(),
			])->dispatch();
		}

		$this->info("Queued callback {$callback->id} for "
			. $listeners->count() . ' listener(s).');

		return self::SUCCESS;
	}

	// =========================================================================
	// scheduled_out is noon at the origin on the departure date, so
	// matchesFlightDate() keeps it in the sync. fa_flight_id is required; use
	// the flight's real one if FlightAware has sent anything yet.
	private function makeCallback(Watch $watch): WatchCallback {
		$flight = $watch->flight;
		$tz = $flight->origin?->timezone ?? 'UTC';
		$scheduledOut = Carbon::parse($flight->departure_date->toDateString() . ' 12:00', $tz)->utc();
		$faFlightId = $watch->callbacks()->where('event_code', '!=', 'test')->latest('id')->value('fa_flight_id');

		$callback = new WatchCallback([
			'alert_id' =>			$watch->subscription_id,
			'event_code' =>			'test',
			'fa_flight_id' =>		$faFlightId ?? 'test',
			'summary' =>			"Test notification for {$flight->flight}",
			'short_description' =>	'This is a test from ChippyTrip. Nothing has changed with your flight.',
			'ident' =>				$flight->flight,
			'ident_iata' =>			$flight->flight,
			'origin' =>				$flight->origin_icao,
			'origin_icao' =>		$flight->origin_icao,
			'origin_iata' =>		$flight->origin?->iata,
			'origin_name' =>		$flight->origin?->name,
			'origin_city' =>		$flight->origin?->city,
			'destination' =>		$flight->destination_icao,
			'destination_icao' =>	$flight->destination_icao,
			'destination_iata' =>	$flight->destination?->iata,
			'destination_name' =>	$flight->destination?->name,
			'destination_city' =>	$flight->destination?->city,
			'scheduled_out' =>		$scheduledOut,
			'position_only' =>		false,
			'blocked' =>			false,
			'cancelled' =>			false,
			'diverted' =>			false,
			'raw_payload' =>		[],
		]);
		$callback->watch_id = $watch->id;
		$callback->save();

		return $callback;
	}
}
