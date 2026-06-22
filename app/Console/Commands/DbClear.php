<?php

namespace App\Console\Commands;

use App\Models\Flight;
use App\Models\Listener;
use App\Models\Watch;
use App\Models\WatchCallback;
use App\Services\FlightAwareSvc;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;

// =============================================================================
#[Signature('db:clear')]
#[Description('Delete flights, listeners & watches from DB and FA')]
class DbClear extends Command {
	// =========================================================================
    public function handle(FlightAwareSvc $fa) {
		foreach(Listener::all() as $l) {
			$l->delete();
		}

		foreach(Watch::all() as $w) {
			$w->delete();
		}

		foreach(Flight::all() as $f) {
			$f->delete();
		}

		foreach($fa->watchList() as $alert) {
			$fa->watchDelete($alert->id);
		}

		WatchCallback::chunk(200, function($cbs) {
			foreach($cbs as $cb) {
				$cb->delete();
			}
		});

		DatabaseNotification::chunk(200, function($notifications) {
			foreach($notifications as $notification) {
				$notification->delete();
			}
		});
    }
}
