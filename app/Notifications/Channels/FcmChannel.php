<php

use \Kreait\Firebase\Contract\Messaging;

// =============================================================================
class FcmChannel {
    public function __construct(private Messaging $messaging) {}

	// =========================================================================
    public function send($notifiable, Notification $notification) {
        $data = $notification->toFcm($notifiable); // ['token' => ..., 'title' => ..., 'body' => ...]

        $message = CloudMessage::withTarget('token', $data['token'])
            ->withNotification(FcmNotification::create($data['title'], $data['body']));

        $this->messaging->send($message);
    }
}
