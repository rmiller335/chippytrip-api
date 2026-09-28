<?php

namespace App\Notifications;

use App\Models\WatchCallback;
use App\Traits\Callback;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// =============================================================================
// Sent by notifications:test, never by FlightAware.
class Test extends Notification {
	use Queueable, Callback;

	// =========================================================================
	public function __construct(protected WatchCallback $callback) {
	}

	// =========================================================================
	public function toMail(object $notifiable): MailMessage {
		return (new MailMessage)
			->subject($this->callback->title)
			->line($this->callback->body)
		;
	}
}
