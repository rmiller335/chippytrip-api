<?php

return [
    // Firebase project ID — Firebase Console > Project Settings.
    'project_id' => env('FCM_PROJECT_ID'),

    // Absolute path to the service-account JSON used to sign FCM v1 requests.
    // Keep this OFF the device build and out of version control.
    // A relative path is taken from the app root: the queue worker doesn't run
    // from there, so a relative path only worked in artisan/tinker.
    'credentials' => (fn (?string $p) => $p && ! str_starts_with($p, '/') ? base_path($p) : $p)(
        env('FIREBASE_CREDENTIALS')
    ),

    /*
    |--------------------------------------------------------------------------
    | Allowed background events (security)
    |--------------------------------------------------------------------------
    | The FCM `data.event` key names the event class dispatched on the device.
    | When this list is non-empty, the background `native:push:dispatch` command
    | will ONLY instantiate classes named here — preventing a sender from
    | triggering arbitrary class construction in the ephemeral runtime.
    | Empty array = allow any class (convenient, less safe).
    |
    | NOTE: this guards the background/ephemeral path only. The foreground path
    | runs through core's POST /_native/api/events route, which this cannot gate.
    */
    'allowed_events' => [
        // \Lumi\NativePush\Events\PushNotificationReceived::class,
        // \App\Events\DataSyncRequested::class,
    ],
];
