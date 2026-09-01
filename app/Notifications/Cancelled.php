<?php

namespace App\Notifications;

use App\Models\WatchCallback;
use App\Traits\Callback;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

// =============================================================================
class Cancelled extends Notification {
    use Queueable, Callback;

    // =========================================================================
    public function __construct(protected WatchCallback $callback) {
    }

    // =========================================================================
    public function toMail(object $notifiable): MailMessage {
        return (new MailMessage)
            ->subject($this->callback->summary)
            ->line($this->callback->long_description)
        ;
    }
}
