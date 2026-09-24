# ChippyTrip API

All endpoints are under `/api`. Authenticated endpoints need a Sanctum token
(`Authorization: Bearer <token>`) and `Accept: application/json`.

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

## Health check

```
GET /health
```

Checks everything the API depends on. It's meant for uptime monitors, not
the app. No authentication.

### Responses

| Status | Body `status` | When |
|---|---|---|
| `200 OK` | `ok` | Every check passed. |
| `200 OK` | `degraded` | Something needs attention, but the API works: a non-critical check failed, or a check warned. |
| `503 Service Unavailable` | `down` | A critical check failed. |

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

### Details

By default each check only shows its `status`. Send the `HEALTH_TOKEN`
value as `X-Health-Token` to also get each check's `message`, timing (`ms`)
and details:

```
GET /health HTTP/1.1
X-Health-Token: <HEALTH_TOKEN>
```

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
