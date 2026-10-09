<?php
namespace App\Notifications\Channels;

use App\Models\UserChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Lumi\NativePush\Server\{FcmSender, FcmMessage};

// =============================================================================
class FcmChannel {
	// Outcome of the latest real send, for HealthCheckSvc::pushDelivery().
	public const LAST_SUCCESS_KEY =	'health:push_last_success';
	public const LAST_FAILURE_KEY =	'health:push_last_failure';

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
            // The `url` extra lets the NativePHP shell deep-link a tap on
            // the tray notification with no background PHP. No `event` key,
            // so the client's FCM listener ignores it.
            try {
                $message = FcmMessage::make()->to($token)->notification(
                    $data['title'] ?? 'ChippyTrip',
                    $data['body'] ?? ''
                );
                if (!empty($data['alert_id'])) {
                    $message->url("/notifications/open/{$data['alert_id']}");
                }
                $this->sender->send($message);
                Cache::put(self::LAST_SUCCESS_KEY, now()->toIso8601String(), now()->addDays(7));
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

        Cache::put(self::LAST_FAILURE_KEY, [
            'at' =>			now()->toIso8601String(),
            'message' =>	mb_substr($e->getMessage(), 0, 300),
        ], now()->addDays(7));
    }
}
