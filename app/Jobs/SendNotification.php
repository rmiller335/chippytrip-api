<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\WatchCallback;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

// =============================================================================
class SendNotification implements ShouldQueue {
    use Queueable;

	// =========================================================================
    public function __construct(
		public WatchCallback $callback,
		public User $user
	) {
    }

	// =========================================================================
    public function handle(): void {
		$notification = $this->callback->notification();

		if(null != $notification) {
			$this->user->notify($notification);
		}
		else {
			Log::warning("No notification for $this->callback->event_code");
		}
    }
}
