# Auth & family invitations: implementation plan

> Also kept as a doc at https://claude.ai/code/artifact/14244889-af55-4fb8-8b46-14676f52719c. Update both when the plan changes.

Plan for building [auth-and-family-invitations.md](auth-and-family-invitations.md) in chippytrip-api. It follows the design's build order. Each phase is a separate PR that ships on its own, with its tests, `docs/api.md` updates and a `php artisan schema:dump` if it adds migrations.

The chippytrack app repo isn't on this machine (`~/dev/chippytrip` is the older Livewire web app). App work is listed at the end of each phase so the API contract stays in sync, but it's scoped less tightly than the API work.

## Findings from the current code

The design doesn't cover these. Each one changes how a phase is built.

| # | Finding | Where | Consequence |
| --- | --- | --- | --- |
| 1 | `FamilyMemberController::update()` calls `sync()` on `family()`. If `family()` gets `wherePivot('status', 'accepted')`, `sync()` only sees accepted rows. It then re-attaches pending rows (unique key violation on `user_id, family_member_id`) and never detaches them. | `app/Http/Controllers/FamilyMemberController.php:55` | `update()` and `index()` must use `familyIncludingPending()`. Add a test that re-PUTs a list containing a pending member. |
| 2 | `FlightWatchSvc::removeListener()` builds its delete list from `family()`. Once that's filtered, unwatching leaves listeners belonging to pending or declined members. | `app/Services/FlightWatchSvc.php:66` | Switch it to `familyIncludingPending()`. |
| 3 | `listeners.user_id` has no `ON DELETE CASCADE`. | schema dump, `listeners_user_id_foreign` | `DELETE /api/me` and `MergeUsers` have to move or delete listeners explicitly, or the user delete fails. |
| 4 | Deleting listeners directly skips watch cleanup. `maintenance:nightly` removes watches with no listeners, but only at night, and the FlightAware alert stays live until then. | `FlightWatchSvc::removeListener()` comment | `DELETE /api/me`, decline and leave call the existing removal path per watch rather than raw deletes. |
| 5 | Postmark can't send mail yet. `symfony/postmark-mailer` isn't installed, `.env.example` has `MAIL_MAILER=log`, and there is no `app/Mail`. Postmark is only used for inbound today. | `composer.lock`, `config/mail.php` | Phase 2 adds the mailer (`composer require symfony/postmark-mailer symfony/http-client`) and the first outbound message. |
| 6 | All routes are in `routes/api.php`, which is stateless and has no CSRF middleware. | `bootstrap/app.php` | The design's "exclude the OAuth callback from CSRF" needs no code. Just confirm Apple's `form_post` reaches the route. |
| 7 | Anyone who registers with a placeholder's email claims it and would see that person's pending invitations. | design, "Password sign-up" step 2 | Without an invite token, the claimed account's pending invitations stay hidden until the email is verified. See Phase 2 and decision D1. |
| 8 | Email verification has no endpoint in the design. `User` doesn't implement `MustVerifyEmail`, and there are no web routes to land on. | `app/Models/User.php` | Add `GET /api/auth/verify-email/{id}/{hash}` (signed URL) and `POST /api/auth/verify-email/resend`. |
| 9 | The one-token-per-device logic lives inline in `Authorizer::genToken()`. `register` and `oauth/exchange` need it too. | `app/Http/Controllers/Authorizer.php:31` | Extract it to `App\Actions\IssueDeviceToken` in Phase 2. |
| 10 | There is no `config/chippytrip.php`. | `config/` | Phase 4 creates it for `beta_gate`. Invite TTL and the invite link host can go there as well. |
| 11 | Claiming a placeholder through OAuth "takes the email from the provider" (design, rule 3). That email may already belong to another user, and `users.email` is unique. | design, "Who the callback signs in" | If the provider email is taken, keep the placeholder's email and store the provider email only on `social_identities`. |

## Phase 1: Consent gating

API only. The current app build won't notice any change.

**Migration** `2026_09_29_120000_add_status_to_family_members.php`
- `status` enum `pending|accepted|declined`, default `pending`; `accepted_at` nullable timestamp.
- In the same migration, backfill: `DB::table('family_members')->update(['status' => 'accepted', 'accepted_at' => DB::raw('created_at')])`.
- Add an index on (`family_member_id`, `status`) for "invitations addressed to me".
- `down()` drops both columns.

**Models**
- `User::family()`: add `->wherePivot('status', 'accepted')` and put `status` and `accepted_at` in `withPivot`.
- `User::familyIncludingPending()`: the same relation without the `wherePivot`.
- `User::isClaimed()`: `$this->password !== null`. Phase 5 extends it to `|| $this->socialIdentities()->exists()`. Add it now so every caller already goes through it.
- `FamilyMember`: casts and fillable for `status` and `accepted_at`, plus scopes `pending()` and `accepted()`.

**Callers**
- `FamilyMemberController::index()` and `update()`: switch to `familyIncludingPending()` (finding 1). `sync()` already leaves pivot columns it isn't given unchanged, so existing rows keep their status and new rows get the DB default, `pending`.
- `FlightWatchSvc::removeListener()`: switch to `familyIncludingPending()` (finding 2).
- `FlightWatchSvc::addAutoFamilyListeners()` and `WatchListenerController`: no change. They use `family()`, which is now filtered.
- `FamilyMemberResource`: add `status`.

**Tests** (`tests/Safe/...`, MySQL: `phpunit -c phpunit.xml tests/Safe`)
- Migration backfill: existing rows come out `accepted`.
- Auto-add skips a pending member (watch via `POST /api/watches`).
- `PUT /api/watches/{id}/listeners` with a pending member's email returns 422.
- `PUT /api/family-members` twice with a pending member: no exception, status stays `pending` (finding 1).
- Unwatch removes a pending member's stray listener (finding 2).
- `GET /api/family-members` lists pending rows with `status`.

**Docs:** `status` on family rows in `docs/api.md`.

**Note:** until Phase 3 ships, there's no way to accept. Anyone added in between stays pending, and the current app has no way to show it. Ship Phases 1 and 3 close together, or hold Phase 1 until Phase 3 is ready (decision D4).

## Phase 2: Password sign-up and sign-out

**Packages and config:** `symfony/postmark-mailer`, `symfony/http-client`, and `MAIL_MAILER=postmark` on servers (finding 5).

**Actions** (new `app/Actions/`)
- `IssueDeviceToken::handle(User $user, string $deviceName): string`: moved from `Authorizer::genToken()` (finding 9). `genToken()` calls it and still returns plain text.
- `ClaimOrCreateUser::handle(string $email, array $attrs): User`. It finds the user by lowercased email. If the user is unclaimed, it fills in `name` and `password` and keeps the id. If claimed, it throws a 422. Otherwise it creates the user. Keep `subscription_type` as it is until D2 is decided.

**Controller** `App\Http\Controllers\Auth\RegisterController`, `PasswordController`, `SessionController` (or a single `AuthController`; the repo leans toward one controller per concern)
- `POST /api/auth/register`, with `throttle:login`. Validation: `name` required, `email` required, `password` using `Password::defaults()` (set in `AppServiceProvider`, min 8), `device_name` required. `invite_token` is accepted and ignored until Phase 3. Returns `{token, user}` with 201.
- `POST /api/auth/logout`: deletes `$request->user()->currentAccessToken()` and the `user_channels` row where `identifier` = the token's `name`. Returns 204.
- `POST /api/auth/logout-all`: deletes all tokens and all FCM channels. Returns 204.
- `POST /api/auth/forgot-password`: `Password::sendResetLink()`, always 202. Uses a custom `ResetPassword` notification whose URL goes to the app/web reset page (decision D3).
- `POST /api/auth/reset-password`: `Password::reset()`, then `$user->tokens()->delete()`. Returns 204.
- `GET /api/auth/verify-email/{id}/{hash}` (signed) and `POST /api/auth/verify-email/resend` (token, throttled) (finding 8). `User implements MustVerifyEmail`, but nothing uses the `verified` middleware, so nothing gets blocked.
- `GET /api/user`: add `has_password` (and `identities` in Phase 5) through a new `UserResource`. The route closure currently returns the raw model.

**Rule from finding 7:** record whether the account was claimed with proof, meaning an invite token or later a verified provider email. The simplest version is to leave `email_verified_at` null on a password claim and let Phase 3's "my invitations" require it.

**Mail:** a `VerifyEmail` notification and a `ResetPassword` notification, sent through Postmark. Decision D5 is Laravel mailables vs Postmark server templates.

**Tests:** register creates a user, claims a placeholder (same id, family rows and listeners kept), returns 422 on a claimed email, and is throttled. Logout removes only this device's token and channel. Logout-all removes everything. Forgot password returns 202 for unknown emails. Reset revokes tokens. The verify link sets `email_verified_at`, and a tampered signature returns 403.

**Docs:** new "Sign up", "Sign out" and "Password reset" sections in `docs/api.md`.

**App:** `App\Actions\CompleteSignIn` pulled out of `Auth\Login`, `Auth\Register`, `Auth\ForgotPassword`, and `Logout` calling the server. Central 401 handling in `ChippyTripApi`.

## Phase 3: Invitations

**Migration** `create_family_invitations_table`, as in the design. The FK on `family_member_id` is a `foreignId()->constrained()->cascadeOnDelete()` on an unsignedBigInteger column. Check the schema dump shows the constraint, because `->constrained()` on the wrong column type silently does nothing in this repo.

**Model and service**
- `FamilyInvitation` with `issue(FamilyMember $link): string` (returns the raw token), `findByToken(string $raw): ?self` (hashes, then checks `expires_at` and `used_at`), and `isLive()`.
- `App\Actions\SendFamilyInvitation`. It rotates the invitation, keeping one live row per link. It mails the invite, and sends an FCM push if the member is claimed. It returns `{invite_url, expires_at}`. In Phase 4 it also calls the beta enrollment.
- `App\Actions\AcceptFamilyInvitation` and `DeclineFamilyInvitation`, each in one transaction. Accept sets `status` and `accepted_at` and marks the invitation used. Decline sets `declined`, marks it used and removes the member's listeners on the owner's watches (finding 4).
- `FamilyMember::invitation()` has-one.

**Controllers and routes**
- `FamilyMemberController::update()`: after `sync()`, call `SendFamilyInvitation` for each newly attached id (`$result['attached']`).
- `POST /api/family-members/{member}/invite`: `{member}` bound through `$request->user()->familyMembers()` so only the owner can reach it. Returns 409 if the link is already accepted.
- `GET /api/invitations/{token}`: public, `throttle:invites`. Returns the preview fields. Returns 410 if expired or used, and 404 for an unknown token. `email_hint` is masked by a helper in `app/Helpers/Utils.php`.
- `POST /api/invitations/{token}/accept`: token auth plus a live invite token. The token proves intent, so the accepting user doesn't have to be the placeholder. If they differ, `MergeUsers` runs when the placeholder is unclaimed (see Phase 5; `MergeUsers` moves to this phase if "accept with a different signed-in account" ships here).
- `GET /api/family-invitations`, `POST /api/family-invitations/{id}/accept|decline`: scoped to `family_member_id = me`. Requires `email_verified_at` when the account was claimed by email only (finding 7).
- `DELETE /api/family-memberships/{owner}`: deletes my row in the owner's list and my listeners on their watches.
- `register` (Phase 2) and `sanctum/token`: a valid `invite_token` accepts in the same transaction.
- `RateLimiter::for('invites', ...)` in `AppServiceProvider`, keyed by IP.
- `FamilyMemberResource`: `invite_url` when pending. The raw token only exists at issue time, so a row can't show its own URL later. Either return it only from `PUT` and `/invite`, or store the token encrypted (not hashed) to show it again. Decision D6.

**Notification:** `App\Notifications\FamilyInvitation` over `FcmChannel`, deep-linking to the invite.

**Web:** `https://chippytrip.com/invite/{token}` fallback page and `/.well-known/assetlinks.json`. Where these live depends on what serves `chippytrip.com` (decision D7).

**Tests:** issue, lookup, expiry (`travelTo`), single use, rotating invalidates the old token, owner-only resend, 410 paths, accept by id and by token, decline removes listeners and disables an orphaned watch, leave, register with `invite_token`, and throttling.

**App:** deep-link scheme and host, the invite landing route, storing `pending_invite` in `TokenStorage`, badges and Share invite in `FamilyMembers`, and the incoming-requests card.

## Phase 4: Beta program

- `config/chippytrip.php` with `beta_gate` (`BETA_GATE`). `config/services.php` gets `firebase.project_number`, `android_app_id`, `app_distribution_key` and `beta_group`.
- Migration `create_beta_testers_table`, as in the design.
- `App\Services\AppDistribution`, using `google/auth` (already a dependency) with a service-account token and the `cloud-platform` scope. Methods `joinGroup(array $emails)` and `removeTesters(array $emails)`. Tests use `Http::fake()`, since `preventStrayRequests()` is on.
- `App\Actions\EnsureBetaAccess::check(string $email, ?string $inviteToken)`. It passes when the gate is off, the email is an active tester, or the invite token is live. Otherwise it throws a 403 `{"error":"beta_closed"}`. It is called from `register` and, in Phase 5, the OAuth "create user" branch. Claiming an existing placeholder isn't a new account, so it isn't gated (decision D8).
- Commands `beta:invite`, `beta:remove` and `beta:list`, thin wrappers over the service. Follow the style of `app/Console/Commands/NotificationsTest.php`.
- `SendFamilyInvitation`: while the gate is on, upsert `beta_testers` with `source = family` and join the Firebase group.
- Welcome email (same mailer as Phase 2).
- **Tests:** gate on and off, removed tester blocked, family invite bypass, commands with a faked API, and Firebase errors reported without a half-written row.
- **Ops:** the Firebase console steps from the design, and the service-account JSON on `madsci`.

## Phase 5: Google

- `composer require laravel/socialite`, and `services.google`.
- Migrations `create_social_identities_table` and `create_oauth_exchange_codes_table`.
- `User::socialIdentities()`, and `isClaimed()` extended.
- `App\Http\Controllers\Auth\OAuthController`:
  - `start`: validates the provider (`google|apple`), `code_challenge` (43–128 chars, base64url), `intent` and the optional `invite_token`/`link_ticket`. It puts them into `Crypt::encryptString(json)` with an issued-at time, then redirects with `Socialite::driver($p)->stateless()->with(['state' => $state])->redirect()`.
  - `callback`: decrypts `state` and rejects it after 10 minutes. It fetches the provider user, runs `ResolveOAuthUser` (the six rules), stores an `oauth_exchange_codes` row (60 s) and redirects to `chippytrack://auth/callback?code=…`. Errors redirect to `?error=<slug>`, never to a JSON page.
  - `exchange`: `throttle:oauth`. It checks `hash_equals(base64url(sha256(verifier)), challenge)`, single use and expiry, then calls `IssueDeviceToken` and returns `{token, user}`.
- `App\Actions\ResolveOAuthUser`: the design's rules 1–6, each a small private method, tested one by one. Rule 5 trusts Google only when `email_verified` is true. Finding 11 applies to rule 3.
- `App\Actions\MergeUsers(User $placeholder, User $into)`: refuses unless `! $placeholder->isClaimed()`. It moves listeners (skipping watches `$into` is already on), moves `family_members` on both sides (skipping duplicates and self-links), moves `user_channels`, deletes the placeholder's tokens (the morph `tokenable`, no FK) and then the placeholder. One transaction.
- Data from before 2026-09-11 may use `App\Models\User` as a `tokenable_type`. Delete tokens by `tokenable_id` with both type spellings, or rely on the `unique_token_per_device` migration having normalized them (it did for tokens).
- `RateLimiter::for('oauth', ...)`.
- **Tests:** each resolution rule; PKCE mismatch, reuse and expiry; tampered or expired `state`; and merge cases (overlapping watches, overlapping family, refusal on a claimed account). Mock Socialite with `Socialite::shouldReceive('driver->stateless->user')`.
- **App:** PKCE verifier in `TokenStorage`, `Browser::auth()`, and the `auth/callback` route calling `oauthExchange()` then `CompleteSignIn`.

## Phase 6: Apple

- `composer require socialiteproviders/apple`, and register the provider in `AppServiceProvider::boot()` as the design says. Add `services.apple`.
- The callback route accepts POST (`Route::match(['get','post'], ...)`). Apple sends `user` (name and email JSON) only on the first authorization. Read it from the request before calling Socialite and pass it into `ResolveOAuthUser`.
- Rule 5: Apple emails count as verified. Private-relay addresses are stored as `provider_email` and don't match existing users unless they are identical.
- **Ops:** Services ID, key `.p8` on the server, Postmark domain registered with Apple's Private Email Relay.
- **Tests:** a POST callback with the `user` field on the first sign-in, a later sign-in without it, and a relay email.

## Phase 7: Account management

- `POST /api/me/identities/link-ticket`: `Crypt`-encrypted `{user_id, exp: +2m}`. `ResolveOAuthUser` rule 2 checks it.
- `DELETE /api/me/identities/{provider}`: returns 409 when it's the last sign-in method (no password and no other identity).
- `PUT /api/me/password`: `current_password` required when `has_password`. Revokes the other devices' tokens.
- `DELETE /api/me`: for each watch the user listens to, run `FlightWatchSvc::removeListener()` (finding 4). Remove the user's listeners on others' watches, delete tokens and channels, then delete the user. `family_members` rows go by cascade both ways, and `password_reset_tokens` too. Test it against a user with listeners, since that FK is the one without a cascade (finding 3).
- `UserResource`: `identities: [provider]`.
- **App:** `Settings\Profile` identities and password, `DeleteUserForm`.

## Cross-cutting

- **Morph map:** no new entries needed, as the design says.
- **Schema dump:** run `php artisan schema:dump` after each migration PR. It was found stale on 2026-09-23.
- **Throttles:** `login` (existing), `invites` and `oauth`. Put their numbers in `config/auth.php` next to `login_rate_limit`.
- **Email normalization:** lowercase emails on register, family PUT and beta tables. `FamilyMemberController::update()` doesn't lowercase today, so `Alice@x.com` and `alice@x.com` can create two placeholders. Fix it in Phase 1 or 3.
- **Logging:** use the existing `Log::` patterns for claim, merge and accept events, since these are the flows that are hard to reconstruct later.

## Decisions needed

| # | Question | Default if not answered |
| --- | --- | --- |
| D1 | Should an account claimed only by typing an email (no invite token, no verified provider email) see that placeholder's pending invitations before verifying the email? | No. Invitations stay hidden until the email is verified. |
| D2 | `subscription_type` for plain sign-ups, and for a claimed `family` placeholder (design open question) | Plain sign-up = `free`, claim keeps `family`. |
| D3 | Where does the password-reset link land: an app deep link, or a web page on `chippytrip.com`? | App deep link `chippytrack://reset-password?token=…&email=…`. |
| D4 | Ship Phase 1 alone, or together with Phase 3? Alone means new family adds are stuck pending in between. | Together. |
| D5 | Outbound email as Laravel mailables in the repo, or Postmark server templates (the design says "templates")? | Laravel mailables through the Postmark transport, so they're versioned and testable. |
| D6 | Should `GET /api/family-members` keep showing `invite_url` for pending rows? That means storing the token encrypted rather than only hashed. | No. Return the URL from `PUT` and `/invite` only; the Share button calls `/invite`, which rotates it. |
| D7 | What serves `chippytrip.com` (the invite fallback page and `assetlinks.json`), and should invite links use `api-dev.chippytrip.com` for now (design open question)? | Serve both from this app on `api-dev` until a production host exists. |
| D8 | Should the beta gate block claiming an existing placeholder with no invite token? | No, a placeholder was already invited by someone. |
| D9 | Can an owner see `declined`, or does it look the same as expired (design open question)? | Owner sees `declined`. |
