# ChippyTrip push notifications (FCM)

Oct 15, 2026 · Robert Miller

## Goal

Every flight alert produces a tray notification **with sound** on the user's phone, whatever state the
app is in: open, backgrounded, or swiped away. Display must not depend on NativePHP cold-booting PHP in
the background.

## How FCM delivers to Android (summary)

- The API sends a message to a device's registration token through the FCM HTTP v1 API. A successful
  response only means FCM queued it.
- Google Play Services delivers it over its shared persistent connection and hands it to the app's
  `FirebaseMessagingService`.
- **Notification part:** when the app is in the background or its process is dead, Android draws it
  in the tray itself. No app code runs. When the app is in the foreground, Android does *not* draw it;
  it calls `onMessageReceived()` and the app must display it.
- **Data part:** always goes to `onMessageReceived()` when the app is in the foreground. When the
  message also has a notification part and the app is in the background, the data is only handed to
  the app as launch-intent extras if the user taps the notification.
- **Data-only messages in the background** require Android to start the process and NativePHP to boot
  PHP within roughly 10–20 seconds. If that fails or is slow, nothing is shown. Repeated high-priority
  messages that produce no visible notification get downgraded to normal priority by Android.
- **Force-stopped apps** (Settings → Force stop, or a swipe-away on some OEM builds) receive nothing
  until the user opens the app again.
- Every value in the `data` map must be a string.

## Current implementation

`App\Notifications\Channels\FcmChannel::send()` sends **two separate messages** per token:

1. A notification-only message built from `toFcm()`'s `title` and `body`, with a `url` extra of
   `/notifications/open/{alert_id}` for tap deep-linking. Android draws it in the background.
2. A data-only message with `event = \App\Events\FlightStatusPushed` and the full `toFcm()` payload.
   On the device, `ShowFlightStatusNotification` uses this to build the notification in the
   foreground, and a `runningInConsole()` gate makes it no-op in the background so it doesn't
   duplicate message 1.

Gaps in this approach:

- The two messages travel independently. Either can be delayed, dropped or throttled without the
  other, so foreground and background behaviour can disagree.
- `Lumi\NativePush\Server\FcmMessage` gives the notification message no `android` block: no `tag`, no
  `channel_id`, no explicit sound or priority settings.
- The tray notification goes to the package's `default` channel (`PushBootstrap.kt`), created with
  `IMPORTANCE_DEFAULT`: it plays a sound but does not pop up as a heads-up notification.
- `toFcm()` doesn't include the callback's `notification_id`, although `WatchCallback` already
  generates one.
- `FcmChannel` drops null values from the payload, so the device can see missing keys.

## Design

One FCM message per token carries both parts. Android displays it in the background; the app displays
it in the foreground. The API remains the record of every notification, and the app syncs from it.

### API side

**1. Record first (already in place).** `WatchCallback` gets its `notification_id` UUID on create, the
`database` channel stores the Laravel notification, and `WatchCallbackSyncResource` exposes the
fields the app's sync needs. No schema change.

**2. Render the final text on the server.** `title` and `body` are finished strings built from the
callback (for example `VS3 scheduled`). The device displays them as given and never rebuilds them
from parts.

**3. Send one message.** Shape of the HTTP v1 request:

```json
{
  "message": {
    "token": "<fcm token>",
    "notification": { "title": "VS3 scheduled", "body": "Departs JFK 14:05" },
    "data": {
      "event": "\\App\\Events\\FlightStatusPushed",
      "notification_id": "<uuid>",
      "alert_id": "123",
      "url": "/notifications/open/123",
      "event_code": "filed"
    },
    "android": {
      "priority": "HIGH",
      "notification": {
        "tag": "<uuid>",
        "channel_id": "flight_alerts",
        "icon": "ic_notification",
        "default_sound": true,
        "notification_priority": "PRIORITY_HIGH"
      }
    }
  }
}
```

- `tag` and `data.notification_id` are the same UUID, so each alert gets its own tray entry and the
  foreground path uses the same identity.
- Every `data` value is a string. The package's `event()`/`data()` already JSON-encode non-strings;
  cast explicitly in `toFcm()` so the device payload is predictable.
- Keep the full flight payload in `data` so the foreground listener and tap handling have it.

**4. Extend the message builder.** `FcmMessage::toArray()` only emits an `android` block (priority
high) for data-only messages and has no tag, channel or sound options. Add
`App\Push\ChippyFcmMessage extends FcmMessage` that merges the `android` block above into the parent's
`toArray()`. `FcmSender::send()` accepts any `FcmMessage`, so no package fork is needed.

**5. Keep dead-token pruning** (`handleSendFailure()` on a 404) unchanged.

### App side (chippytrack)

**Notification channel.** Create a `flight_alerts` channel with `IMPORTANCE_HIGH` and the default
sound at app start, before the first push can arrive. Background pushes name it via
`android.notification.channel_id`; if the channel doesn't exist on the device, Android falls back to
the FCM default channel (the package's `default`). Channel importance and sound can't be changed by
the app once the channel exists, so get them right first time; changing them later means a new
channel id. Check whether `nativephp-mobile-local-notifications` can create channels; if not, add it
to the app's Android bootstrap, as the push package does in `PushBootstrap.kt`. Optionally point
`com.google.firebase.messaging.default_notification_channel_id` at `flight_alerts` as well.

**Behaviour by app state:**

| App state | Who displays | App code runs? |
| --- | --- | --- |
| Foreground | `ShowFlightStatusNotification` via `LocalNotifications::schedule()` | Yes |
| Background, process alive | Android, from the notification part | No |
| Background, process dead / swiped (stock Android) | Android, from the notification part | No |
| Force-stopped | Nothing; history appears at next sync | No |

**Foreground handling.** `FlightStatusPushed` fires; `ShowFlightStatusNotification` calls
`LocalNotifications::schedule()` with `notification_id` as the id, the same title and body, and the
`flight_alerts` channel, then tells the Notifications list to refresh. Keep the `runningInConsole()`
gate as a safeguard against a background duplicate, and confirm early that the push plugin does not
fire the event for combined messages in the background.

**Sync.** On app open and resume, fetch notifications newer than the latest one held locally and upsert
by `notification_id`. The local `FlightNotification` table stays as a cache; `read_at` syncs back to
the API.

**Tap.** The `url` extra deep-links to `/notifications/open/{alert_id}` without background PHP, as
today.

## Edge cases

- **Force-stopped app:** no notification or sound; sync fills in history when the user next opens it.
- **Notification permission denied (Android 13+) or channel muted:** nothing is displayed. Show an
  in-app banner when the permission is missing.
- **Priority downgrades:** every high-priority push now produces a visible notification, so Android
  has no reason to deprioritize later ones.
- **iOS:** out of scope for now. The same single message works for APNs, but sound needs
  `apns.payload.aps.sound` set when iOS is added.

## Implementation checklist

API (`chippytrip-api`):

- [ ] Add `App\Push\ChippyFcmMessage` with the `android` priority/tag/channel/sound block.
- [ ] Add `notification_id` to `Traits/Callback.php::toFcm()` and cast all values to strings.
- [ ] Replace the two sends in `FcmChannel::send()` with one combined message.
- [ ] Feature test asserting the outgoing request body shape.

App (`chippytrack`):

- [ ] Create the `flight_alerts` channel (`IMPORTANCE_HIGH`, default sound) at startup.
- [ ] Use `flight_alerts` and `notification_id` in `ShowFlightStatusNotification`'s `schedule()`.
- [ ] Refresh the Notifications list on `FlightStatusPushed` and on app resume.

## Testing

Run each state with the app open, backgrounded, swiped away, and force-stopped, on the emulator and a
real device, spacing runs 30–60 seconds apart. Expected: one tray entry with sound per alert in the
first three states, none in the fourth, and complete history after reopening in every case.
