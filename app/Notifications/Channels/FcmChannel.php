<?php
namespace App\Notifications\Channels;

use App\Models\UserChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Lumi\NativePush\Server\{FcmSender, FcmMessage};

// =============================================================================
class FcmChannel {
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
            ->all();

        foreach ($channels as $channel) {
            $token = $channel->credentials['token'];

			Log::debug('FcmChannel: sending on channel ' . $channel->id);
			Log::debug(json_encode($data, JSON_PRETTY_PRINT));

            // Notification-only send: OS auto-displays this directly when
            // backgrounded/killed, with zero app code involved — immune to
            // OEM background-execution restrictions (e.g. Motorola).
            try {
                $this->sender->send(
                    FcmMessage::make()->to($token)->notification(
                        $data['title'] ?? 'ChippyTrip',
                        $data['body'] ?? ''
                    )
                );
            } catch (\RuntimeException $e) {
                $this->handleSendFailure($e, $channel, $token, 'notification');
            }

            // Data-only send: drives the in-app notification build when
            // foregrounded (see ShowFlightStatusNotification's
            // runningInConsole() gate — it no-ops in background/killed to
            // avoid a duplicate of the notification-only send above).
            try {
                $this->sender->send(
                    FcmMessage::make()->to($token)->event(
                        '\App\Events\FlightStatusPushed',
                        $data
                    )
                );
            } catch (\RuntimeException $e) {
                $this->handleSendFailure($e, $channel, $token, 'data');
            }
        }
    }

	// =========================================================================
    private function handleSendFailure(
		\RuntimeException $e, UserChannel $channel, string $token, string $kind): void
	{
        if (preg_match('/FCM send failed \((\d+)\)/', $e->getMessage(), $m) && (int) $m[1] === 404) {
            Log::info("FCM: pruning dead token (via {$kind} send)", ['token' => $token]);
            $channel->delete();
            return;
        }

        Log::error("FCM {$kind} send threw: {$e->getMessage()}", [
            'token' => $token,
        ]);
    }
}
