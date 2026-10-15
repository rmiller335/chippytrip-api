# ChippyTrip push notifications (FCM)

Oct 15, 2026 · Robert Miller

## Goal

Every flight alert produces a tray notification **with sound** on the user's phone, whatever state the
app is in: open, backgrounded, or swiped away. Display must not depend on NativePHP cold-booting PHP in
the background. Each device plays one sound for all ChippyTrip notifications, chosen by the user from
a menu of bundled sounds (see [Notification sounds](#notification-sounds)).

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
        "channel_id": "alerts_chime",
        "icon": "ic_notification",
        "notification_priority": "PRIORITY_HIGH"
      }
    },
    "apns": {
      "payload": { "aps": { "sound": "chime.caf" } }
    }
  }
}
```

- `tag` and `data.notification_id` are the same UUID, so each alert gets its own tray entry and the
  foreground path uses the same identity.
- `android.notification.channel_id` and `apns.payload.aps.sound` come from the device's chosen sound
  (see [Notification sounds](#notification-sounds)).
- Every `data` value is a string. The package's `event()`/`data()` already JSON-encode non-strings;
  cast explicitly in `toFcm()` so the device payload is predictable.
- Keep the full flight payload in `data` so the foreground listener and tap handling have it.

**4. Extend the message builder.** `FcmMessage::toArray()` only emits an `android` block (priority
high) for data-only messages and has no tag, channel or sound options. Add
`App\Push\ChippyFcmMessage extends FcmMessage` that merges the `android` block and the APNs `sound`
above into the parent's `toArray()`. `FcmSender::send()` accepts any `FcmMessage`, so no package fork
is needed.

**5. Keep dead-token pruning** (`handleSendFailure()` on a 404) unchanged.

### App side (chippytrack)

**Notification channels.** Create one channel per bundled sound (`alerts_chime`, `alerts_bell`, …,
`alerts_default`), all `IMPORTANCE_HIGH`, at app start, before the first push can arrive. Background
pushes name the device's channel via `android.notification.channel_id`; if that channel doesn't exist
on the device, Android falls back to the FCM default channel (the package's `default`). Channel
importance and sound can't be changed by the app once the channel exists, so get them right first
time; changing them later means a new channel id. Check whether
`nativephp-mobile-local-notifications` can create channels; if not, add it to the app's Android
bootstrap, as the push package does in `PushBootstrap.kt`. Optionally point
`com.google.firebase.messaging.default_notification_channel_id` at `alerts_default` as well.

**Behaviour by app state:**

| App state | Who displays | App code runs? |
| --- | --- | --- |
| Foreground | `ShowFlightStatusNotification` via `LocalNotifications::schedule()` | Yes |
| Background, process alive | Android, from the notification part | No |
| Background, process dead / swiped (stock Android) | Android, from the notification part | No |
| Force-stopped | Nothing; history appears at next sync | No |

**Foreground handling.** `FlightStatusPushed` fires; `ShowFlightStatusNotification` calls
`LocalNotifications::schedule()` with `notification_id` as the id, the same title and body, and the
channel for the locally stored sound choice, then tells the Notifications list to refresh. Keep the `runningInConsole()`
gate as a safeguard against a background duplicate, and confirm early that the push plugin does not
fire the event for combined messages in the background.

**Sync.** On app open and resume, fetch notifications newer than the latest one held locally and upsert
by `notification_id`. The local `FlightNotification` table stays as a cache; `read_at` syncs back to
the API.

**Tap.** The `url` extra deep-links to `/notifications/open/{alert_id}` without background PHP, as
today.

## Notification sounds

Each device plays one sound for all ChippyTrip notifications. The user picks it from a menu in the
app's profile settings. The choice is per device, not per user, so a phone and a tablet can differ.

### Sound set

A small fixed set bundled in both apps, for example Chime, Bell, Ping and System default. A
notification can only play a sound already inside the app; nothing is downloaded later.

| Key | Android (`res/raw`) | iOS (app bundle) | Android channel |
| --- | --- | --- | --- |
| `chime` | `chime.ogg` | `chime.caf` | `alerts_chime` |
| `bell` | `bell.ogg` | `bell.caf` | `alerts_bell` |
| `ping` | `ping.ogg` | `ping.caf` | `alerts_ping` |
| `default` | system default | `default` | `alerts_default` |

Keep each sound short (a second or two). iOS requires under 30 seconds and `.caf`, `.aiff` or `.wav`.
Make one source file per sound and export both formats. The Android files need a build step to copy
them into the generated Android project, as `bin/install-notification-icon` does for the icon.

### Storing the choice

- The app shows the menu, saves the choice locally, and sends it to the API with its FCM token on
  every app open (`FcmTokenController`).
- The API stores it on that device's `user_channels` row (keyed by `device_id` as `identifier`) in a
  new nullable `notification_sound` column. It is not put in `credentials`, because
  `FcmTokenController` replaces `credentials` on every token refresh.
- Unknown or missing values fall back to `default`.

### Sending

`FcmChannel` reads `notification_sound` per token and maps it to:

- **iOS:** `apns.payload.aps.sound` = the bundled file name (`chime.caf`), or `default`. iOS picks
  the sound per notification, so a change applies from the next push.
- **Android:** `android.notification.channel_id` = the matching channel (`alerts_chime`). Android
  ties sound to the channel, so switching sounds switches channels. No channel is ever deleted or
  recreated, which avoids Android's rule that a channel's sound can't change after creation.

In the foreground, `ShowFlightStatusNotification` uses the local setting to pick the same channel for
`schedule()`.

### Side effects and limits

- Android's notification settings for ChippyTrip list one channel per sound. Name them clearly, for
  example "Flight alerts (Chime)".
- If the user changes a channel's sound or mutes it in Android settings, their change wins on that
  device. On iOS, the user can still turn sounds off for the app.
- Previewing a sound in the menu means playing an audio file from the app; check whether the
  NativePHP plugins support this before relying on it. Previews are optional.

## Pre-departure app reminders

A force-stopped Android app receives no pushes until the user opens it again, and nothing the app sets
up survives a force stop. The risk is small for US users: stock Android and Pixel phones only kill the
process on a swipe-away, and the aggressive OEM killers (Xiaomi, OnePlus, Oppo, Huawei) have little US
share. The main exposure is Samsung, which can put apps that haven't been opened for a while into
"sleeping" or "deep sleeping" lists. A flight alert app can easily go weeks unopened.

Watches added in the app need nothing extra: the app has just been opened. Watches added by forwarding a
confirmation email can be weeks old by departure. Opening the app shortly before the flight clears any
stopped state, resets Samsung's idle timer, refreshes the FCM token and runs the sync, so the API emails
a reminder to users whose app hasn't checked in recently.

### Tracking last check-in

- Add a nullable `last_seen_at` timestamp to `user_channels`.
- `FcmTokenController` sets it to `now()` on every call. `updated_at` can't be used for this: when the
  token is unchanged, `updateOrCreate()` has nothing dirty to save, so `updated_at` doesn't move.
- A user's last check-in is the latest `last_seen_at` across their FCM channels.

### Sending the reminder

- A scheduled command runs hourly and finds listeners whose flight departs (`scheduled_out`) in the next
  24 hours and who haven't already been reminded for that flight.
- It skips users with any FCM device seen in the past 3 days.
- Everyone else gets one email naming the flight and asking them to open ChippyTrip before departure so
  alerts arrive. Users with no FCM device at all are asked to install the app and sign in.
- Record the send on the listener (for example `app_reminder_sent_at`) so each flight gets at most one
  reminder.
- The rule depends only on check-in time, not on how the watch was added, so it also covers app users
  who haven't opened the app since adding a watch far in advance.

### Measuring the gap

The API records every push it sends, and the app syncs history on open. Have the app report which
synced notifications it never displayed in the tray, with the device model. That shows whether missed
pushes are a real problem and on which phones, and whether the reminder threshold needs tuning.

## Edge cases

- **Force-stopped app:** no notification or sound; sync fills in history when the user next opens it.
  The pre-departure reminder email reduces how often this happens.
- **Notification permission denied (Android 13+) or channel muted:** nothing is displayed. Show an
  in-app banner when the permission is missing.
- **Priority downgrades:** every high-priority push now produces a visible notification, so Android
  has no reason to deprioritize later ones.
- **iOS:** the same single message works for APNs; the sound comes from `apns.payload.aps.sound`.

## Implementation checklist

API (`chippytrip-api`):

- [ ] Add `App\Push\ChippyFcmMessage` with the `android` priority/tag/channel block and APNs sound.
- [ ] Add `notification_id` to `Traits/Callback.php::toFcm()` and cast all values to strings.
- [ ] Replace the two sends in `FcmChannel::send()` with one combined message.
- [ ] Migration: nullable `notification_sound` on `user_channels`.
- [ ] Accept and validate `notification_sound` in `FcmTokenController`.
- [ ] Map `notification_sound` to `channel_id` and APNs `sound` per token in `FcmChannel`.
- [ ] Feature test asserting the outgoing request body shape, including the sound mapping.
- [ ] Migration: nullable `last_seen_at` on `user_channels`; set it on every `FcmTokenController` call.
- [ ] Migration: nullable `app_reminder_sent_at` on the listener.
- [ ] Hourly command and mailable for the pre-departure reminder, with a feature test for the 3-day skip.

App (`chippytrack`):

- [ ] Bundle the sound files (Android `res/raw`, iOS app bundle) with a build step for Android.
- [ ] Create one `IMPORTANCE_HIGH` channel per sound at startup.
- [ ] Sound menu in profile settings; save locally and send with the FCM token.
- [ ] Use the chosen channel and `notification_id` in `ShowFlightStatusNotification`'s `schedule()`.
- [ ] Refresh the Notifications list on `FlightStatusPushed` and on app resume.
- [ ] Report synced notifications that were never shown in the tray, with the device model.

## Testing

Run each state with the app open, backgrounded, swiped away, and force-stopped, on the emulator and a
real device, spacing runs 30–60 seconds apart. Expected: one tray entry with sound per alert in the
first three states, none in the fourth, and complete history after reopening in every case.

Ongoing coverage comes from the canary's push matrix (chippy-canary `docs/push-matrix.md`): synthetic
pushes to each canary device in each app state, checked by tray tag, channel and in-app list. It
needs a `canary:push-test` artisan command on the API and a `data-notification-id` on the app's
notification rows.
