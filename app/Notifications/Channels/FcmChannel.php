<?php
namespace App\Notifications\Channels;

use App\Models\UserChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lumi\NativePush\Server\{FcmSender, FcmMessage};

// =============================================================================
class FcmChannel {
	// =========================================================================
    public function __construct(private FcmSender $sender) {}

	// =========================================================================
	public function send(object $notifiable, Notification $notification): void {
		$channels = $notifiable->channels()
			->where('channel', self::class)
			->get()
			->filter(fn(UserChannel $c) => !empty($c->credentials['token'] ?? null))
			->values();

		if ($channels->isEmpty()) {
			return;
		}

		$data = collect($notification->toFcm($notifiable))
			->reject(fn($v) => is_null($v))
			->all()
		;

		$data['notification_id'] = (string) Str::uuid();

		foreach ($channels as $channel) {
			$token = $channel->credentials['token'];

			Log::debug('FcmChannel: sending on channel ' . $channel->id);
			Log::debug(json_encode($data, JSON_PRETTY_PRINT));

			try {
				$response = $this->sender->send(
					FcmMessage::make()->to($token)->event('\App\Events\FlightStatusPushed', $data)
				);

				Log::debug('FcmChannel: sent', ['token' => $token, 'response' => $response]);
			} catch (\RuntimeException $e) {
				if (preg_match('/FCM send failed \((\d+)\)/', $e->getMessage(), $m) && (int) $m[1] === 404) {
					Log::info("FCM: pruning dead token", ['token' => $token]);
					$channel->delete();
					continue;
				}
	
				Log::error("FCM send threw: {$e->getMessage()}", [
					'notifiable_id' => $notifiable->id ?? null,
					'token' => $token,
				]);
			}
		}
	}
}
