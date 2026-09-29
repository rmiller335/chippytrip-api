# Auth & family invitations: implementation plan

> Also kept as a doc at https://claude.ai/code/artifact/14244889-af55-4fb8-8b46-14676f52719c. Update both when the plan changes.

This plan builds [auth-and-family-invitations.md](auth-and-family-invitations.md) in chippytrip-api in seven phases, following the design's build order. Each phase is a separate PR that ships on its own, with its tests, `docs/api.md` updates and a `php artisan schema:dump` if it adds migrations. The chippytrack app repo isn't on this machine, so app work is listed per phase but scoped less tightly than the API work.

## Findings from the current code

The design doesn't cover these 11 points. Each one changes how a phase is built.

| # | Finding | Where | Consequence |
| --- | --- | --- | --- |
| 1 | `FamilyMemberController::update()` calls `sync()` on `family()`. `sync()` applies the relation's `wherePivot` filters (checked in the framework source), so once `family()` is filtered to accepted it re-attaches pending rows (unique key violation) and never detaches them. | `FamilyMemberController.php:55` | `update()` and `index()` use `familyIncludingPending()`. Test re-PUTting a list with a pending member. |
| 2 | `FlightWatchSvc::removeListener()` builds its delete list from `family()`. Once filtered, unwatching leaves pending or declined members' listeners. | `FlightWatchSvc.php:66` | Switch it to `familyIncludingPending()`. |
| 3 | `listeners.user_id` has no `ON DELETE CASCADE`. | schema dump | `DELETE /api/me` and `MergeUsers` move or delete listeners explicitly, or the user delete fails. |
| 4 | Deleting listeners directly skips watch cleanup; the FlightAware alert stays live until `maintenance:nightly`. | `FlightWatchSvc::removeListener()` | `DELETE /api/me`, decline and leave go through the existing removal path per watch. |
| 5 | Postmark can't send mail yet: `symfony/postmark-mailer` isn't installed, `MAIL_MAILER=log`, no `app/Mail`. | `composer.lock`, `config/mail.php` | Phase 2 adds the mailer and the first outbound message. |
| 6 | All routes are in `routes/api.php`, which is stateless and has no CSRF middleware. | `bootstrap/app.php` | The OAuth callback needs no CSRF exclusion; just confirm Apple's `form_post` reaches it. |
| 7 | Anyone who registers with a placeholder's email claims it and would see its pending invitations. | design, Password sign-up step 2 | Without an invite token, those invitations stay hidden until the email is verified. |
| 8 | Email verification has no endpoint in the design; `User` doesn't implement `MustVerifyEmail`. | `app/Models/User.php` | Add `GET /api/auth/verify-email/{id}/{hash}` (signed) and `POST /api/auth/verify-email/resend`. |
| 9 | One-token-per-device logic is inline in `Authorizer::genToken()`; `register` and `oauth/exchange` need it too. | `Authorizer.php:31` | Extract `App\Actions\IssueDeviceToken` in Phase 2. |
| 10 | There is no `config/chippytrip.php`. | `config/` | Phase 3 creates it for the invite TTL and link host; Phase 4 adds `beta_gate`. |
| 11 | OAuth claim "takes the email from the provider", which may already belong to another user; `users.email` is unique. | design, rule 3 | If taken, keep the placeholder's email and store the provider email only on `social_identities`. |

## Phase 1: Consent gating

API only; the current app build won't notice any change.

**Migration** `add_status_to_family_members`

- `status` enum `pending|accepted|declined`, default `pending`; `accepted_at` nullable timestamp.
- Backfill in the same migration: every existing row becomes `accepted`, with `accepted_at = created_at`.
- Index on (`family_member_id`, `status`) for "invitations addressed to me". `down()` drops both columns.

**Models**

- `User::family()`: add `->wherePivot('status', 'accepted')`; put `status` and `accepted_at` in `withPivot`.
- `User::familyIncludingPending()`: the same relation without the filter.
- `User::isClaimed()`: `password !== null` for now; Phase 5 adds `|| socialIdentities()->exists()`. Add it now so every caller already goes through it.
- `FamilyMember`: casts and fillable for `status` and `accepted_at`, plus `pending()` and `accepted()` scopes.

**Callers**

- `FamilyMemberController::index()` and `update()` switch to `familyIncludingPending()` (finding 1). `sync()` leaves pivot columns it isn't given, so existing rows keep their status and new rows get `pending`.
- `FlightWatchSvc::removeListener()` switches to `familyIncludingPending()` (finding 2).
- `addAutoFamilyListeners()` and `WatchListenerController`: no change; they use the now-filtered `family()`.
- `FamilyMemberResource` gains `status`.

**Tests** (MySQL: `phpunit -c phpunit.xml tests/Safe`)

- Backfill turns existing rows `accepted`.
- Auto-add skips a pending member.
- `PUT /api/watches/{id}/listeners` with a pending email returns 422.
- `PUT /api/family-members` twice with a pending member: no exception, status stays `pending`.
- Unwatch removes a pending member's stray listener.
- `GET /api/family-members` lists pending rows with `status`.

**Docs:** `status` on family rows in `docs/api.md`.

Phases 1 and 3 go out in the same deploy. Without Phase 3 there is no way to accept, so anyone added in between would stay pending.

## Phase 2: Password sign-up and sign-out

**Packages:** `symfony/postmark-mailer`, `symfony/http-client`; `MAIL_MAILER=postmark` on servers (finding 5).

**Actions** (new `app/Actions/`)

- `IssueDeviceToken::handle(User, string $deviceName): string`, moved from `Authorizer::genToken()` (finding 9). `genToken()` calls it and still returns plain text.
- `ClaimOrCreateUser::handle(string $email, array $attrs): User`: finds by lowercased email; claims an unclaimed user in place (same id), throws 422 for a claimed one, else creates. New users get `subscription_type = free`; claiming a `family` placeholder keeps `family`.

**Endpoints**

| Endpoint | Behavior |
| --- | --- |
| `POST /api/auth/register` | `throttle:login`; `Password::defaults()` (min 8); `invite_token` accepted but ignored until Phase 3; 201 `{token, user}` |
| `POST /api/auth/logout` | Deletes the current token and the `user_channels` row whose `identifier` is the token's `name`; 204 |
| `POST /api/auth/logout-all` | Deletes all tokens and FCM channels; 204 |
| `POST /api/auth/forgot-password` | `Password::sendResetLink()`, always 202; the link is the app deep link `chippytrack://reset-password?token=…&email=…` |
| `POST /api/auth/reset-password` | `Password::reset()`, then revoke all tokens; 204 |
| `GET /api/auth/verify-email/{id}/{hash}` | Signed URL; sets `email_verified_at` (finding 8) |
| `POST /api/auth/verify-email/resend` | Token auth, throttled |
| `GET /api/user` | New `UserResource` adding `has_password`; the route closure returns the raw model today |

`User` implements `MustVerifyEmail`, but nothing uses the `verified` middleware, so nothing is blocked. A password claim leaves `email_verified_at` null, and Phase 3 hides that account's pending invitations until it is verified (finding 7).

**Mail:** Laravel notifications and mailables in the repo, sent over the Postmark transport, not Postmark server templates. `VerifyEmail` and `ResetPassword` come first; the templates are versioned and testable with `Notification::fake()` and `Mail::fake()`.

**Tests:** register creates a `free` user, claims a placeholder (same id, `family` kept, family rows and listeners kept), 422 on a claimed email, throttled. Logout removes only this device's token and channel; logout-all removes everything. Forgot password returns 202 for unknown emails and the link uses the deep-link scheme. Reset revokes tokens. A tampered verify signature returns 403.

**App:** `CompleteSignIn` pulled out of `Auth\Login`, `Auth\Register`, `Auth\ForgotPassword`, a new `Auth\ResetPassword` screen on the `reset-password` deep link, server logout, central 401 handling in `ChippyTripApi`.

## Phase 3: Invitations

**Migration** `create_family_invitations_table` as designed. Check the schema dump shows the `family_member_id` FK: `->constrained()` on the wrong column type silently does nothing in this repo.

**Config:** new `config/chippytrip.php` with `invite_ttl_days` (7). Invite links are built from `APP_URL` with `url('/invite/'.$token)`, so they are `https://api-dev.chippytrip.com/invite/{token}` today; moving to a production host is an `APP_URL` change plus the app's `NATIVEPHP_DEEPLINK_HOST`. Set `APP_URL` correctly on every server, since queued mail and pushes build links from it.

**Model and actions**

- `FamilyInvitation`: `issue(FamilyMember): string` (returns the raw token), `findByToken(string): ?self` (hash, then check `expires_at` and `used_at`), `isLive()`. `FamilyMember::invitation()` has-one. Only the hash is stored.
- `SendFamilyInvitation`: rotates to one live row per link, mails the invite, pushes over FCM if the member is claimed, returns `{invite_url, expires_at}`. Phase 4 adds beta enrollment here.
- `AcceptFamilyInvitation` and `DeclineFamilyInvitation`, one transaction each. Decline sets `declined`, which the owner sees, and removes the member's listeners on the owner's watches through the removal path (finding 4).

**Endpoints**

| Endpoint | Behavior |
| --- | --- |
| `PUT /api/family-members` | After `sync()`, `SendFamilyInvitation` for each id in `$result['attached']`; new rows in the response carry `invite_url` |
| `GET /api/family-members` | Rows carry `status` (`pending`, `accepted` or `declined`) but no `invite_url`, since only the hash is stored |
| `POST /api/family-members/{member}/invite` | Bound through `$request->user()->familyMembers()`. Rotates the token and resends; a `declined` row goes back to `pending`. 409 if already accepted. The app's Share invite button calls this. |
| `GET /api/invitations/{token}` | Public, `throttle:invites`; 410 expired or used, 404 unknown; `email_hint` masked by a helper in `Utils.php` |
| `POST /api/invitations/{token}/accept` | Token auth plus a live invite token; merges an unclaimed placeholder into the caller if they differ (pulls `MergeUsers` forward from Phase 5) |
| `GET /api/family-invitations`, `POST .../{id}/accept\|decline` | Scoped to `family_member_id = me`. Empty for an account claimed by email alone until `email_verified_at` is set (finding 7). |
| `DELETE /api/family-memberships/{owner}` | Deletes my row in their list and my listeners on their watches |
| `register`, `sanctum/token` | A valid `invite_token` accepts in the same transaction |

The design listed `invite_url` on every pending row of `GET /api/family-members`; this plan drops that so raw tokens are never stored.

- `RateLimiter::for('invites')` in `AppServiceProvider`, keyed by IP.
- `App\Notifications\FamilyInvitation` over `FcmChannel`, deep-linking to the invite.
- **Web routes in this app:** add `web:` routing in `bootstrap/app.php` (outside the Sanctum `api` group, like `/health`) for `GET /invite/{token}`, a plain fallback page with install steps and the token to paste, and `GET /.well-known/assetlinks.json` for `com.chippytrip.chippytrack`, with fingerprints from config.

**Tests:** issue, lookup, expiry (`travelTo`), single use, rotation invalidates the old token, owner-only resend, a declined row returns to pending on resend, 410 paths, accept by id and by token, invitations hidden before verification for email-only claims, decline removes listeners and disables an orphaned watch, leave, register with `invite_token`, throttling, the fallback page and `assetlinks.json`.

**App:** `NATIVEPHP_DEEPLINK_HOST=api-dev.chippytrip.com`, invite landing route, `pending_invite` in `TokenStorage`, Pending, Accepted and Declined badges plus Share invite in `FamilyMembers`, incoming-requests card.

## Phase 4: Beta program

- **Config:** `config/chippytrip.php` (from Phase 3) gains `beta_gate` (`BETA_GATE`). `config/services.php` gains `firebase.project_number`, `android_app_id`, `app_distribution_key`, `beta_group`.
- **Migration:** `create_beta_testers_table` as designed.
- **Service:** `App\Services\AppDistribution` on `google/auth` (already a dependency) with the `cloud-platform` scope; `joinGroup(array $emails)` and `removeTesters(array $emails)`. Tests use `Http::fake()`, since `preventStrayRequests()` is on.
- **Gate:** `App\Actions\EnsureBetaAccess::check(string $email, ?string $inviteToken)` passes when the gate is off, the email is an active tester, or the invite token is live; otherwise 403 `{"error":"beta_closed"}`. Called from `register` and, in Phase 5, the OAuth create-user branch. Claiming an existing placeholder is never gated, since someone already invited that person.
- **Commands:** `beta:invite`, `beta:remove`, `beta:list`, thin wrappers in the style of `NotificationsTest.php`.
- **Family invites:** while the gate is on, `SendFamilyInvitation` upserts `beta_testers` with `source = family` and joins the Firebase group.
- **Mail:** welcome email as a mailable, same Postmark transport as Phase 2.
- **Tests:** gate on and off, removed tester blocked, family invite bypass, placeholder claim passes with the gate on, commands against a faked API, Firebase errors reported without a half-written row.
- **Ops:** the Firebase console steps from the design; service-account JSON on `madsci`.

## Phase 5: Google

- **Setup:** `composer require laravel/socialite`; `services.google`. Migrations `create_social_identities_table` and `create_oauth_exchange_codes_table`. `User::socialIdentities()`; `isClaimed()` extended.
- **`OAuthController::start`:** validates provider (`google|apple`), `code_challenge` (43–128 chars, base64url), `intent` and optional `invite_token`/`link_ticket`. Packs them with an issued-at time into `Crypt::encryptString(json)` and redirects via `Socialite::driver($p)->stateless()->with(['state' => $state])->redirect()`.
- **`callback`:** decrypts `state` (reject after 10 minutes), fetches the provider user, runs `ResolveOAuthUser`, stores a 60 s `oauth_exchange_codes` row and redirects to `chippytrack://auth/callback?code=…`. Errors redirect to `?error=<slug>`, never a JSON page.
- **`exchange`:** `throttle:oauth`; checks `hash_equals(base64url(sha256(verifier)), challenge)`, single use and expiry, then `IssueDeviceToken`; returns `{token, user}`.
- **`App\Actions\ResolveOAuthUser`:** design rules 1–6, one small private method each, tested one by one. Rule 5 trusts Google only when `email_verified` is true; finding 11 applies to rule 3.
- **`App\Actions\MergeUsers(User $placeholder, User $into)`:** refuses unless the placeholder is unclaimed. Moves listeners (skipping watches `$into` is on), `family_members` both sides (skipping duplicates and self-links), `user_channels`; deletes the placeholder's tokens (morph, no FK) and the placeholder. One transaction. Delete tokens by `tokenable_id` for both `user` and legacy `App\Models\User` types.
- `RateLimiter::for('oauth')`.
- **Tests:** each resolution rule; PKCE mismatch, reuse, expiry; tampered or expired `state`; merges with overlapping watches and family; refusal on a claimed account. Mock with `Socialite::shouldReceive('driver->stateless->user')`.
- **App:** PKCE verifier in `TokenStorage`, `Browser::auth()`, `auth/callback` route calling `oauthExchange()` then `CompleteSignIn`.

## Phase 6: Apple

- **Setup:** `composer require socialiteproviders/apple`; register the provider in `AppServiceProvider::boot()` as designed; `services.apple`.
- **Callback:** `Route::match(['get','post'], ...)`. Apple sends `user` (name and email JSON) only on the first authorization; read it from the request before calling Socialite and pass it to `ResolveOAuthUser`.
- **Rule 5:** Apple emails count as verified. Private-relay addresses are stored as `provider_email` and match existing users only if identical.
- **Ops:** Services ID, `.p8` key on the server, Postmark domain registered with Apple's Private Email Relay.
- **Tests:** first sign-in POST with `user`, later sign-in without it, relay email.

## Phase 7: Account management

- `POST /api/me/identities/link-ticket`: `Crypt`-encrypted `{user_id, exp: +2m}`, checked by `ResolveOAuthUser` rule 2.
- `DELETE /api/me/identities/{provider}`: 409 when it's the last sign-in method (no password, no other identity).
- `PUT /api/me/password`: `current_password` required when `has_password`; revokes other devices' tokens.
- `DELETE /api/me`: run `FlightWatchSvc::removeListener()` for each watch the user listens to (finding 4), remove their listeners on others' watches, delete tokens and channels, then the user. `family_members` and `password_reset_tokens` go by cascade. Test with a user who has listeners, since that FK doesn't cascade (finding 3).
- `UserResource` gains `identities: [provider]`.
- **App:** `Settings\Profile` identities and password, `DeleteUserForm`.

## Cross-cutting

- **Morph map:** no new entries needed, as the design says.
- **Schema dump:** `php artisan schema:dump` after each migration PR; it was found stale on 2026-09-23.
- **Throttles:** `login` (existing), `invites`, `oauth`; their numbers go in `config/auth.php` beside `login_rate_limit`.
- **Email normalization:** lowercase on register, family PUT and beta tables. `FamilyMemberController::update()` doesn't today, so `Alice@x.com` and `alice@x.com` can create two placeholders. Fix in Phase 1 or 3.
- **Logging:** log claim, merge and accept events with the existing `Log::` patterns; these flows are hard to reconstruct later.

## Decisions

All nine were settled on 2026-09-29 by taking the proposed default. The phases above are written on that basis.

| # | Question | Decision |
| --- | --- | --- |
| D1 | Should an account claimed only by typing an email see that placeholder's pending invitations before verifying it? | No; hidden until verified |
| D2 | `subscription_type` for plain sign-ups and for a claimed `family` placeholder | Sign-up = `free`; claim keeps `family` |
| D3 | Where does the password-reset link land? | App deep link `chippytrack://reset-password?token=…&email=…` |
| D4 | Ship Phase 1 alone, or with Phase 3? | Same deploy |
| D5 | Outbound email as Laravel mailables or Postmark server templates? | Mailables over the Postmark transport |
| D6 | Keep showing `invite_url` on pending rows in `GET /api/family-members`? | No; URL only from `PUT` and `/invite` |
| D7 | What serves the invite page and `assetlinks.json`, and on which host? | This app, at its `APP_URL` (`api-dev.chippytrip.com` today) |
| D8 | Should the beta gate block claiming an existing placeholder without an invite token? | No |
| D9 | Can an owner see `declined`? | Yes |

D2, D7 and D9 were open questions in the design doc and are now recorded there as decided.
