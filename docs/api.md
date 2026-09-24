# ChippyTrip API

The API behind the ChippyTrip mobile app. The app signs in, searches for
flights, watches them, and syncs its flights and notifications. FlightAware
and Postmark call it through webhooks.

- [Conventions](#conventions)
- [Authentication](#authentication)
- [Endpoints](#endpoints)
- Account: [current user](#get-the-current-user), [register for push notifications](#register-for-push-notifications)
- Flights: [search airports](#search-airports), [search airlines](#search-airlines), [look up a flight number](#look-up-a-flight-number), [search by route](#search-flights-by-route), [watch a flight](#watch-a-flight), [stop watching](#stop-watching-a-flight)
- Family: [list](#list-family-members), [replace](#replace-family-members), [a flight's family listeners](#list-a-flights-family-listeners), [replace them](#replace-a-flights-family-listeners)
- [Sync](#sync)
- [Webhooks](#webhooks)
- [Health check](#health-check)

## Conventions

- All endpoints are under `/api`, except `/health`. There are no other
  routes; `/` returns `404`.
- Send `Accept: application/json`. Request bodies are JSON
  (`Content-Type: application/json`).
- Every response is JSON, errors included, whatever the `Accept` header
  says. The one exception is [`POST /api/sanctum/token`](#get-a-token),
  which returns the token as plain text.
- Timestamps are UTC. Most are ISO 8601 (`2026-09-22T16:05:00+00:00` or
  `2026-09-22T16:05:00.000000Z`). The flight lookup and route search
  return `YYYY-MM-DD HH:MM`, also in UTC but without a marker.
- Airports are identified by ICAO code (`KJFK`) and airlines by ICAO code
  (`UAL`). IATA codes (`JFK`, `UA`) are included where known and can be
  `null`.

### Errors

| Status | Body | When |
|---|---|---|
| `401 Unauthorized` | `{"message": "Unauthenticated."}` | Missing, invalid or revoked token. Never a redirect. |
| `403 Forbidden` | `{"message": ""}` | Signed in, but not allowed to use this resource. |
| `429 Too Many Requests` | `{"message": "Too Many Attempts."}` | Rate limited ([sign-in](#authentication) only). |
| `404 Not Found` | `{"message": "…"}` | Unknown route, or the resource in the path doesn't exist. |
| `422 Unprocessable Content` | `{"message": "…", "errors": {"field": ["…"]}}` | Validation failed. `errors` is keyed by field; nested fields use dots (`listeners.0.email`). |
| `502 Bad Gateway` | `{"message": "Could not reach FlightAware."}` | A FlightAware call failed (flight lookups, route search, watching a flight). |
| `500 Internal Server Error` | `{"message": "Server Error"}` | A bug. |

In development (`APP_DEBUG=true`) error bodies also include `exception`,
`file`, `line` and `trace`. Don't rely on them.

## Authentication

The API uses [Laravel Sanctum](https://laravel.com/docs/sanctum) personal
access tokens.

1. Exchange the user's email and password for a token with
   [`POST /api/sanctum/token`](#get-a-token). Send a stable ID for the
   device as `device_name`, the same one used as `device_id` for
   [push notifications](#register-for-push-notifications).
2. Send the token on every other request:

   ```
   Authorization: Bearer 1|ENQ6GJwplYirmC0XiZzujjO7DHsLFT6Nq3R1m6Eq949bc7f3
   ```

3. Store it in the device's secure storage (Keychain / Keystore).

Things to know:

- **One token per device.** Signing in again with the same `device_name`
  replaces that device's token; the old one stops working.
- **Tokens don't expire.** A token works until it's replaced or deleted on
  the server. There's no sign-out or revoke endpoint yet, so signing out on
  the device means forgetting the token.
- **Sign-in is rate limited**, to `LOGIN_RATE_LIMIT` attempts a minute per
  email address from one IP (default 5) and `LOGIN_RATE_LIMIT_PER_IP` a
  minute from one IP in total (default 20). Failed and successful attempts
  both count. Over the limit, the endpoint returns `429` with a
  `Retry-After` header in seconds.
- **A `401` means the token is no good.** Discard it and ask the user to
  sign in again.
- **There's no sign-up endpoint.** Accounts are created on the server.
- **Family members can't sign in.** Adding someone with
  [`PUT /api/family-members`](#replace-family-members) creates an account
  for their email with no password, so `POST /api/sanctum/token` rejects it.
- `/health` uses its own header, not a Sanctum token. See
  [Health check](#health-check).
- The webhooks authenticate differently. See [Webhooks](#webhooks).

### Get a token

```
POST /api/sanctum/token
```

No authentication.

| Field | Type | Required | Description |
|---|---|---|---|
| `email` | string | yes | |
| `password` | string | yes | |
| `device_name` | string | yes | A stable ID for the device, up to 255 characters. Signing in again with the same value replaces the device's token. |

| Status | Body | When |
|---|---|---|
| `200 OK` | The token, **as plain text** (`Content-Type: text/html`), not JSON | Signed in. |
| `422` | `{"message": "The provided credentials are incorrect.", "errors": {"email": [...]}}` | Wrong email or password. The same error for both. |
| `422` | Validation errors | A field is missing. |
| `429 Too Many Requests` | `{"message": "Too Many Attempts."}` | Over the [rate limit](#authentication). Retry after `Retry-After` seconds. |

```
POST /api/sanctum/token HTTP/1.1
Content-Type: application/json
Accept: application/json

{"email": "pat@example.com", "password": "…", "device_name": "Pixel 8"}

HTTP/1.1 200 OK
Content-Type: text/html; charset=utf-8

1|ENQ6GJwplYirmC0XiZzujjO7DHsLFT6Nq3R1m6Eq949bc7f3
```

Use the whole string, including the `1|` prefix, as the bearer token.

## Endpoints

| Method | Path | Auth | Description |
|---|---|---|---|
| `POST` | [`/api/sanctum/token`](#get-a-token) | — | Sign in and get a token |
| `GET` | [`/api/user`](#get-the-current-user) | token | The signed-in user |
| `POST` | [`/api/fcm-token`](#register-for-push-notifications) | token | Register a device for push notifications |
| `GET` | [`/api/airports/search`](#search-airports) | token | Airport type-ahead |
| `GET` | [`/api/airlines/search`](#search-airlines) | token | Airline type-ahead |
| `POST` | [`/api/flights/validate`](#look-up-a-flight-number) | token | Look up a flight number on a date |
| `POST` | [`/api/flights/search`](#search-flights-by-route) | token | Scheduled flights on a route and date |
| `POST` | [`/api/watches`](#watch-a-flight) | token | Watch a flight |
| `DELETE` | [`/api/flights/{flight_id}`](#stop-watching-a-flight) | token | Stop watching a flight |
| `GET` | [`/api/family-members`](#list-family-members) | token | The user's family members |
| `PUT` | [`/api/family-members`](#replace-family-members) | token | Replace the family members |
| `GET` | [`/api/watches/{watch_id}/listeners`](#list-a-flights-family-listeners) | token | Family members getting a flight's notifications |
| `PUT` | [`/api/watches/{watch_id}/listeners`](#replace-a-flights-family-listeners) | token | Replace them |
| `GET` | [`/api/sync/listeners`](#sync) | token | Everything the app stores locally |
| `POST` | [`/api/watch-callback`](#flightaware-alerts) | secret | FlightAware alert webhook |
| `POST` | [`/api/postmark/inbound`](#forwarded-confirmation-emails) | basic auth | Postmark inbound email webhook |
| `GET` | [`/health`](#health-check) | health token | Health check for uptime monitors |

## Get the current user

```
GET /api/user
```

```json
{
  "id": 1,
  "name": "Pat Doe",
  "email": "pat@example.com",
  "email_verified_at": "2026-09-20T12:00:00.000000Z",
  "subscription_type": "free",
  "created_at": "2026-09-20T12:00:00.000000Z",
  "updated_at": "2026-09-20T12:00:00.000000Z"
}
```

`subscription_type` is `free`, or `family` for accounts created by adding a
family member. `email_verified_at` can be `null`.

## Register for push notifications

```
POST /api/fcm-token
```

Registers the device's Firebase Cloud Messaging token. Call it after
signing in and whenever FCM issues a new token.

| Field | Type | Required | Description |
|---|---|---|---|
| `token` | string | yes | The FCM registration token. |
| `device_id` | string | yes | A stable ID for this device. Calling again with the same `device_id` replaces its token. |

Returns `204 No Content`.

Each device gets two FCM messages per flight event:

- A notification message with `title` and `body`, which the OS shows
  when the app is in the background or not running.
- A data message with event `\App\Events\FlightStatusPushed`, carrying
  `title`, `body`, `event_code`, `alert_id`, the flight and airport codes,
  and the scheduled, estimated and actual times. Null fields are left out.

`title` and `body` are the same text as
[`flight_notifications[].title` and `body`](#flight_notifications) in the
sync. If FCM reports a token as no longer registered, the server removes
it.

## Search airports

```
GET /api/airports/search?q={text}
```

Type-ahead for airport fields. `q` (required) matches the start of an IATA
or ICAO code, or any part of the name or city. Exact code matches come
first, then code prefixes, then name and city matches, then by name. At
most 15 results.

```json
[
  { "icao": "KJFK", "iata": "JFK", "name": "John F Kennedy Intl", "city": "New York" }
]
```

Returns `[]` when nothing matches, and `422` without `q`.

## Search airlines

```
GET /api/airlines/search?q={text}
```

Like [airport search](#search-airports), matching IATA and ICAO code
prefixes and any part of the name. At most 15 results.

```json
[
  { "icao": "UAL", "iata": "UA", "name": "United Airlines" }
]
```

## Look up a flight number

```
POST /api/flights/validate
```

Finds a flight number's scheduled flights on a date, to confirm it exists
before watching it.

| Field | Type | Required | Description |
|---|---|---|---|
| `flight_number` | string | yes | IATA (`UA100`) or ICAO (`UAL100`). Case and surrounding spaces don't matter. |
| `date` | date | yes | `YYYY-MM-DD`, today or later. |

Returns an array, usually of one flight. It can have several when the same
number flies more than once that day (e.g. there and back), and is `[]` when
the number doesn't fly that day.

```json
[
  {
    "flight_number": "UA100",
    "flight_no": "100",
    "airline_icao": "UAL",
    "airline_iata": "UA",
    "origin_icao": "KSFO",
    "origin_iata": "SFO",
    "destination_icao": "KJFK",
    "destination_iata": "JFK",
    "departure_time": "2026-09-22 16:00",
    "arrival_time": "2026-09-23 00:30"
  }
]
```

| Field | Description |
|---|---|
| `flight_number` | IATA flight number if FlightAware has one, otherwise ICAO. Pass this to [`POST /api/watches`](#watch-a-flight). |
| `flight_no` | The number without the airline code. |
| `airline_icao`, `airline_iata` | `airline_iata` is `null` without an IATA flight number. |
| `origin_*`, `destination_*` | ICAO and IATA codes. IATA can be `null`. |
| `departure_time`, `arrival_time` | Scheduled gate departure and arrival, **UTC**, `YYYY-MM-DD HH:MM`. `null` if unknown. |

Errors: `422` for a missing field or a past date, `502` if FlightAware
can't be reached.

## Search flights by route

```
POST /api/flights/search
```

Scheduled flights between two airports on a date.

| Field | Type | Required | Description |
|---|---|---|---|
| `origin` | string | yes | Airport code, e.g. `KSFO`. |
| `destination` | string | yes | Airport code, e.g. `KJFK`. |
| `date` | date | yes | `YYYY-MM-DD`, today or later. |
| `airline` | string | no | Only this airline's flights. Airline code, e.g. `UAL`. |

Returns an array of flights in the same shape as
[flight lookup](#look-up-a-flight-number), each with three more fields
from the server's own tables:

| Field | Description |
|---|---|
| `origin_name`, `destination_name` | Airport names; `""` if the airport isn't known. |
| `airline_name` | `null` if the airline isn't known. |

Returns `[]` when nothing flies the route that day. Errors: `422`, `502`
as for flight lookup.

## Watch a flight

```
POST /api/watches
```

Starts sending the user notifications for a flight. Usually called with a
result from [flight lookup](#look-up-a-flight-number) or
[route search](#search-flights-by-route).

| Field | Type | Required | Description |
|---|---|---|---|
| `flight_number` | string | yes | IATA (`UA100`) or ICAO (`UAL100`). The airline code must be one the server knows. |
| `origin` | string | yes | Origin airport ICAO code, e.g. `KSFO`. |
| `destination` | string | yes | Destination airport ICAO code. |
| `date` | date | yes | Local departure date, `YYYY-MM-DD`, today or later. |
| `travelers` | string | no | Who's flying, shown with the flight. Defaults to the user's name. |

```json
{
  "flight": {
    "id": 1,
    "airline_icao": "UAL",
    "flight_no": "100",
    "flight": "UA100",
    "origin_icao": "KSFO",
    "destination_icao": "KJFK",
    "departure_date": "2026-09-22T00:00:00.000000Z",
    "departure_dt": "2026-09-22T16:00:00.000000Z",
    "arrival_dt": "2026-09-23T00:30:00.000000Z"
  },
  "watch": { "id": 1, "flight_id": 1, "subscription_id": null, "enabled": false },
  "listener": { "id": 1, "watch_id": 1, "travelers": "Pat Doe" }
}
```

The objects have the same shape as in the [sync](#sync).

| Status | When |
|---|---|
| `200 OK` | Watching. Also returned if the user was already watching; nothing is duplicated. |
| `404` | `{"message": "No matching scheduled flight found."}`: FlightAware has no such flight on that date and route. |
| `422` | A field is missing, the date is in the past, or the airline code in `flight_number` isn't recognised (`errors.flight_number`). |
| `502` | FlightAware can't be reached. |

Effects:

- Flights are shared. If anyone already watches the same flight, the
  user joins that watch instead of creating a new one. Flights are stored
  under their IATA flight number, so `UAL100` and `UA100` are the same
  flight.
- Family members marked `auto_add` also start getting this flight's
  notifications. See [family members](#replace-family-members).
- Notifications start once the flight is within its alert window, from the
  day before departure to two days after. `watch.enabled` is `false` until
  then, and for a moment after watching a flight that's already in the
  window, while the alert is set up.

## Stop watching a flight

```
DELETE /api/flights/{flight_id}
```

Removes the signed-in user from a flight they're watching.

### Path parameter

| Name | Type | Description |
|---|---|---|
| `flight_id` | integer | The flight's `id`. In the `/api/sync/listeners` response it's `flights[].id`; from a listener, follow `listeners[].watch_id` → `watches[].flight_id`. |

### Request body

None.

### Responses

| Status | Body | When |
|---|---|---|
| `204 No Content` | empty | Removed. |
| `401 Unauthorized` | JSON error | Missing or invalid token. |
| `404 Not Found` | JSON error | The flight doesn't exist, or the user isn't watching it. The two cases look the same on purpose. |

Base client handling on the status code only; the 404 body's `message`
differs between the two cases.

### Effects

- Removes the user's listener on the flight.
- Also removes the listeners of everyone in the user's family list on that
  flight, so they stop getting its notifications too.
- Other users watching the same flight aren't affected.
- The flight itself isn't deleted by the request. `maintenance:nightly`
  deletes it, with its notifications, once nobody is watching it.

### Client handling

- On `204`, remove the flight and its notifications from local storage, or
  run a sync. The next `/api/sync/listeners` response won't include it.
- Treat `404` as already removed and delete it locally. It can happen if the
  flight was removed on another device.
- Calling the endpoint twice is safe; the second call returns `404`.
- The user can add the flight again at any time with `POST /api/watches`.
  Before the nightly cleanup runs, that reuses the existing flight.

### Example

```
DELETE /api/flights/166 HTTP/1.1
Authorization: Bearer 12|abc…
Accept: application/json

HTTP/1.1 204 No Content
```

## List family members

```
GET /api/family-members
```

```json
{
  "data": [
    { "id": 2, "name": "Mom", "email": "mom@example.com", "auto_add": true },
    { "id": 3, "name": "Sam", "email": "sam@example.com", "auto_add": false }
  ]
}
```

| Field | Description |
|---|---|
| `id` | The family member's user ID. |
| `name` | The name this user gave them. Each user names their family members independently. |
| `auto_add` | If `true`, they're added to every flight this user watches from now on. |

Note the `data` wrapper, which the other endpoints don't have.

## Replace family members

```
PUT /api/family-members
```

Replaces the whole list. Send every family member each time; anyone left
out is removed.

```json
{
  "family": [
    { "name": "Mom", "email": "mom@example.com", "auto_add": true },
    { "name": "Sam", "email": "sam@example.com" }
  ]
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `family` | array | yes | Can be `[]` to remove everyone. |
| `family[].name` | string | yes | |
| `family[].email` | string | yes | |
| `family[].auto_add` | boolean | no | Defaults to `false`. |

Returns the new list, as in [list family members](#list-family-members).

- An email without an account gets one, with no password. The person
  can't sign in with it (see [Authentication](#authentication)), so they
  can't register a device and **don't get push notifications yet**. Their
  notifications are only stored on the server.
- The user's own email is ignored.
- Removing someone doesn't remove them from flights they were already
  added to.

## List a flight's family listeners

```
GET /api/watches/{watch_id}/listeners
```

The user's family members who get notifications for this flight.
`watch_id` is `watches[].id` in the sync. The user themselves and other
people watching the same flight aren't included.

```json
{ "data": [ { "email": "mom@example.com", "name": "Mom" } ] }
```

Returns `403` if the user isn't watching this flight and `404` if the
watch doesn't exist.

## Replace a flight's family listeners

```
PUT /api/watches/{watch_id}/listeners
```

Sets which of the user's family members get this flight's notifications.
Send the complete list.

```json
{ "listeners": [ { "email": "sam@example.com" } ] }
```

| Field | Type | Required | Description |
|---|---|---|---|
| `listeners` | array | yes | Can be `[]` to remove all family members from the flight. |
| `listeners[].email` | string | yes | Must be one of the user's [family members](#list-family-members). |

Returns the new list, as in the `GET`. Only the user's own family members
are added or removed; the user's own listener and anyone else watching the
flight are untouched.

| Status | When |
|---|---|
| `403` | The user isn't watching this flight. |
| `404` | The watch doesn't exist. |
| `422` | An email isn't one of the user's family members: `"The selected email is not one of your family members."` |

## Sync

```
GET /api/sync/listeners
```

Everything the app keeps locally: the user's watched flights, their
notifications, and the airlines and airports they refer to. **Each
response is complete.** Replace local data with it; anything not in the
response should be deleted locally.

Only flights that depart today or later, or departed within the last
`SYNC_PAST_DAYS` days (default 14), are included. Older flights drop out
on their own.

```json
{
  "synced_at": "2026-09-20T12:00:00+00:00",
  "listeners": [ … ],
  "watches": [ … ],
  "flight_notifications": [ … ],
  "flights": [ … ],
  "airlines": [ … ],
  "airports": [ … ]
}
```

How the pieces connect:

```
listeners[].watch_id  → watches[].id
watches[].flight_id   → flights[].id
flights[].airline_icao, origin_icao, destination_icao → airlines[].icao, airports[].icao
flight_notifications[].flight_id → flights[].id
```

### listeners

One per flight the user watches.

| Field | Type | Description |
|---|---|---|
| `id` | integer | |
| `watch_id` | integer | |
| `travelers` | string | Who's flying, as entered when watching. |

### watches

| Field | Type | Description |
|---|---|---|
| `id` | integer | Use it with [`/api/watches/{watch_id}/listeners`](#list-a-flights-family-listeners). |
| `flight_id` | integer | |
| `subscription_id` | string\|null | FlightAware alert ID. `null` when not enabled. |
| `enabled` | boolean | `true` while FlightAware is sending alerts, from the day before departure to two days after. |

### flights

| Field | Type | Description |
|---|---|---|
| `id` | integer | Use it with [`DELETE /api/flights/{flight_id}`](#stop-watching-a-flight). |
| `flight` | string | IATA flight number, e.g. `UA100`. |
| `flight_no` | string | The number without the airline code. |
| `airline_icao` | string | |
| `origin_icao`, `destination_icao` | string | |
| `departure_date` | string | The local departure date, as midnight UTC (`2026-09-22T00:00:00.000000Z`). Use only the date part. |
| `departure_dt`, `arrival_dt` | string\|null | Scheduled gate departure and arrival, UTC. |

### flight_notifications

One per FlightAware event for the flight, e.g. filed, left the gate,
took off, landed, delayed or cancelled. They're in no particular order;
sort by `event_dt`.

| Field | Type | Description |
|---|---|---|
| `id` | integer | |
| `notification_id` | string | UUID. Stable; use it to match a push notification to its row. |
| `flight_id` | integer | |
| `alert_id` | integer | FlightAware alert ID. |
| `event_code` | string | `filed`, `out`, `departure`, `arrival`, `in`, `change`, `cancelled`, `diverted`, … |
| `event_dt` | string | When the event happened, UTC. |
| `title`, `body` | string | The notification text, as sent in the push, e.g. "UA100 took off from SFO" / "Arrives JFK 8:25 PM (5 min early) at Terminal 7." Times are local to the airport. |
| `summary`, `short_description`, `long_description` | string\|null | FlightAware's own text, for a detail view. `long_description` has several lines. |
| `cancelled`, `diverted` | boolean | |
| `flight_number` | string | IATA flight number. |
| `airline_icao` | string | The watched flight's airline. |
| `origin_*`, `destination_*` | string\|null | `_iata`, `_icao`, `_name`, `_city` of the watched flight's airports. |
| `scheduled_off`, `estimated_off`, `actual_off` | string\|null | Takeoff times, UTC. |
| `scheduled_on`, `estimated_on`, `actual_on` | string\|null | Landing times, UTC. |
| `scheduled_in`, `estimated_in`, `actual_in` | string\|null | Gate arrival times, UTC. |
| `scheduled_out`, `estimated_out`, `actual_out` | string\|null | Gate departure times, UTC. |
| `created_at`, `updated_at` | string | |

### airlines

`icao`, `iata`, `name` for each airline in `flights`.

### airports

| Field | Type | Description |
|---|---|---|
| `icao`, `iata`, `name`, `city` | string\|null | |
| `display_name` | string\|null | A shorter name, when set. |
| `timezone` | string | IANA timezone, e.g. `America/New_York`. Use it to show flight times in local time. |

### Example

```json
{
  "synced_at": "2026-09-20T12:00:00+00:00",
  "listeners": [ { "id": 1, "watch_id": 1, "travelers": "Pat Doe" } ],
  "watches": [ { "id": 1, "flight_id": 1, "subscription_id": "1234567", "enabled": true } ],
  "flight_notifications": [
    {
      "id": 1,
      "notification_id": "49789656-2a64-42ad-bc30-0ad3feeff62e",
      "flight_id": 1,
      "alert_id": 1234567,
      "event_code": "departure",
      "event_dt": "2026-09-22T16:05:00+00:00",
      "title": "UA100 took off from SFO",
      "body": "Arrives JFK 8:25 PM (5 min early) at Terminal 7.",
      "summary": "UAL100 has departed SFO for JFK",
      "short_description": "UAL100 (B77W) departed SFO @ 09:20AM PDT for JFK ETA 05:25PM EDT",
      "long_description": "United Airlines #100 (B77W) departed …",
      "cancelled": false,
      "diverted": false,
      "flight_number": "UA100",
      "airline_icao": "UAL",
      "origin_iata": "SFO", "origin_icao": "KSFO", "origin_name": "San Francisco Intl", "origin_city": "San Francisco",
      "destination_iata": "JFK", "destination_icao": "KJFK", "destination_name": "John F Kennedy Intl", "destination_city": "New York",
      "scheduled_off": "2026-09-22T16:15:00.000000Z", "estimated_off": null, "actual_off": "2026-09-22T16:20:00.000000Z",
      "scheduled_on": null, "estimated_on": null, "actual_on": null,
      "scheduled_in": "2026-09-23T00:30:00.000000Z", "estimated_in": "2026-09-23T00:25:00.000000Z", "actual_in": null,
      "scheduled_out": "2026-09-22T16:00:00.000000Z", "estimated_out": null, "actual_out": "2026-09-22T16:05:00.000000Z",
      "created_at": "2026-09-22T16:21:00+00:00",
      "updated_at": "2026-09-22T16:21:00+00:00"
    }
  ],
  "flights": [
    {
      "id": 1, "airline_icao": "UAL", "flight_no": "100", "flight": "UA100",
      "origin_icao": "KSFO", "destination_icao": "KJFK",
      "departure_date": "2026-09-22T00:00:00.000000Z",
      "departure_dt": "2026-09-22T16:00:00.000000Z",
      "arrival_dt": "2026-09-23T00:30:00.000000Z"
    }
  ],
  "airlines": [ { "icao": "UAL", "iata": "UA", "name": "United Airlines" } ],
  "airports": [
    { "icao": "KSFO", "iata": "SFO", "name": "San Francisco Intl", "display_name": null, "city": "San Francisco", "timezone": "America/Los_Angeles" },
    { "icao": "KJFK", "iata": "JFK", "name": "John F Kennedy Intl", "display_name": null, "city": "New York", "timezone": "America/New_York" }
  ]
}
```

## Webhooks

Called by other services, not the app.

### FlightAware alerts

```
POST /api/watch-callback?s={secret}
```

FlightAware AeroAPI posts here when a watched flight has an event. Each
watch gets its own alert and random secret when it's enabled; the URL
FlightAware is given (`FLIGHTAWARE_CALLBACK`) includes it as `s`.

- The body's `alert_id` must match a watch's `subscription_id`, and `s`
  must match that watch's secret. Otherwise it returns `403` with the plain
  text `Invalid POST request`.
- The event is stored. If it's for the watched flight's date, every
  listener on the watch is sent a notification.
- Returns `200` with an empty body.

### Forwarded confirmation emails

```
POST /api/postmark/inbound
```

Postmark posts here when a user forwards a booking confirmation to the
inbound email address (e.g. `plans@chippytrip.com`). This URL is set as
the inbound webhook in Postmark's server settings. It uses Postmark's
inbound JSON: `FromFull.Email` (required), `Subject`, `TextBody`,
`HtmlBody`, `MessageID`.

`php artisan flight:test-email` posts a sample confirmation to
`POSTMARK_INBOUND_URL` in the same format, to test parsing without
sending an email. It sends the basic-auth credentials itself, so leave
them out of that URL.

- If the sender's email belongs to a user (ignoring case), the email is
  stored and parsed (with OpenAI) in the background, and the flights found
  are watched for that user.
- Emails from anyone else are discarded without being stored.
- A Postmark retry with the same `MessageID` is stored and parsed once.
- Returns `200 OK` (plain text).

It requires HTTP basic auth, user `POSTMARK_INBOUND_USER` (default
`inbound`) and password `POSTMARK_INBOUND_PASSWORD`. Put them in the
inbound webhook URL in Postmark's server settings:

```
https://inbound:<POSTMARK_INBOUND_PASSWORD>@api.chippytrip.com/api/postmark/inbound
```

Without them, or if `POSTMARK_INBOUND_PASSWORD` isn't set, it returns
`401` and nothing is stored.

## Health check

```
GET /health
```

Checks everything the API depends on. It's meant for uptime monitors, not
the app. Send the `HEALTH_TOKEN` value as the `X-Health-Token` header:

```
GET /health HTTP/1.1
X-Health-Token: <HEALTH_TOKEN>
```

### Responses

| Status | Body `status` | When |
|---|---|---|
| `200 OK` | `ok` | Every check passed. |
| `200 OK` | `degraded` | Something needs attention, but the API works: a non-critical check failed, or a check warned. |
| `503 Service Unavailable` | `down` | A critical check failed. |
| `404 Not Found` | — | The token is missing or wrong, or `HEALTH_TOKEN` isn't set. No checks run. |

Alert on `503`. `degraded` is worth a look but shouldn't page anyone.

### Checks

| Check | Critical | Fails when |
|---|---|---|
| `app` | yes | `APP_KEY` isn't set. Warns if `APP_DEBUG` is on in production. |
| `database` | yes | The database can't be queried. |
| `migrations` | yes | A migration hasn't been run. |
| `cache` | yes | A value can't be written and read back. |
| `storage` | yes | Files can't be written to the default disk, `storage/logs`, `storage/framework/cache` or `bootstrap/cache` isn't writable, or free disk is below `HEALTH_MIN_FREE_DISK_MB`. |
| `queue` | yes | A job has waited longer than `HEALTH_QUEUE_MAX_WAIT` seconds. The check queues a small heartbeat job at most once a minute, so a stopped worker shows up even when nothing else is queued. |
| `failed_jobs` | no | Any job failed in the last 24 hours. |
| `maintenance` | yes | `maintenance:nightly` hasn't finished within `HEALTH_MAINTENANCE_MAX_AGE` seconds. Warns if no run has been recorded yet. |
| `flightaware` | yes | AeroAPI isn't configured or rejects the key (`GET /account/usage`). |
| `fcm` | yes | The Firebase service account can't get a token, or FCM rejects a `validate_only` message. Nothing is delivered. |
| `openai` | no | OpenAI rejects the key or the configured `OPENAI_FLIGHT_MODEL`. Only used for forwarded confirmation emails. |

`flightaware`, `fcm` and `openai` call external APIs. A passing result is
cached for `HEALTH_EXTERNAL_TTL` seconds and a failure for 60 seconds, so
frequent polling doesn't add API usage. Those results include
`"cached": true`.

### Example response

Each check has a `status`, a `message`, its timing (`ms`) and any details:

```json
{
  "status": "ok",
  "checked_at": "2026-09-24T14:50:57+00:00",
  "checks": {
    "database": { "status": "ok", "message": "Connected.", "connection": "mysql", "ms": 12 },
    "queue": { "status": "ok", "message": "Worker is processing jobs.", "pending": 0, "last_heartbeat": "2026-09-24T14:50:54+00:00", "ms": 2 },
    "flightaware": { "status": "ok", "message": "AeroAPI accepted the key.", "ms": 2711 }
  }
}
```

(Other checks omitted.) Settings are in `config/health.php`.
