<?php

namespace App\Notifications\Channels;

use App\Models\UserChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;

// =============================================================================
class FcmChannel {
	// =========================================================================
	public function __construct(private Messaging $messaging) {}

	// =========================================================================
	public function send(object $notifiable, Notification $notification): void {
		$tokens = $notifiable->channels()
			->where('channel', self::class)
			->get()
			->map(fn(UserChannel $c) => $c->credentials['token'] ?? null)
			->filter()
			->values();

		if($tokens->isEmpty()) {
			return;
		}

		$data = $notification->toFcm($notifiable);

		$message = CloudMessage::new()
			->withNotification(FcmNotification::create($data['title'], $data['body']));

		try {
			$report = $this->messaging->sendMulticast($message, $tokens->all());
		}
		catch(\Throwable $e) {
			Log::error("FCM sendMulticast threw: {$e->getMessage()}", [
				'notifiable_id' => $notifiable->id ?? null,
			]);
			return;
		}

		if($report->hasFailures()) {
			$deadTokens = array_merge($report->unknownTokens(), $report->invalidTokens());

			if($deadTokens) {
				Log::info("FCM: pruning dead tokens", ['tokens' => $deadTokens]);

				UserChannel::where('channel', self::class)
					->whereIn('credentials->token', $deadTokens)
					->delete();
			}
		}
	}
}
