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
