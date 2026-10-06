# Beta accounts: addendum

Oct 6, 2026 · Robert Miller

> Extends [auth-and-family-invitations.md](auth-and-family-invitations.md) and
> [auth-implementation-plan.md](auth-implementation-plan.md); it doesn't replace them. Working notes are in the doc at
> https://claude.ai/code/artifact/2f40a35c-2462-44e4-9ff3-23b0c68ba810.

Most of the beta account scenarios are already designed: invitations with consent and signed links, Google and Apple
through `social_identities` keyed on `sub`, merging placeholders, the beta gate, forgot password, leaving a family and
`DELETE /api/me`. This file records what the Oct 6 review added, and two points that need a decision against the
Sep 29 decisions.

## To decide

### 1. Default tier for a sign-up without an invite

The design's decisions say `free`. The Oct 6 scenario list says `basic`. Pick one and update the design's Decisions
section.

### 2. Beta gate and Apple Hide My Email

`EnsureBetaAccess` runs in the OAuth callback before a new user is created, and checks the email the provider returns.
A beta tester who has **no** invite token and signs in with Apple while hiding their email returns a
`…@privaterelay.appleid.com` address, which isn't in `beta_testers`, so they get `beta_closed`.

Options:

- **Email first.** The app asks for the email, `EnsureBetaAccess` checks it, and the API returns a short-lived signed
  `beta_ticket` that rides in the OAuth `state` like `invite_token`. The callback accepts a valid ticket in place of
  the provider email, and links the identity to the email that was checked.
- **Code fallback.** On `beta_closed` with a relay address, the app asks for the real email and sends a 6-digit code;
  a correct code attaches the Apple identity to that tester.

Email first also gives the app the sign-in hint below. Family invitees are unaffected: their invite token already
passes the gate.

## Additions

### Sign-in hint from MX

Show Google, Apple and password to everyone; only emphasize the likely one.

- `GET /api/auth/hint?email=` → `{"hint": "google" | "apple" | null}`, throttled.
- `gmail.com`, `googlemail.com` → `google`; `icloud.com`, `me.com`, `mac.com` → `apple`.
- Otherwise `getmxrr()` on the domain: hosts ending `.google.com` or `.googlemail.com` → `google`; `.mail.icloud.com`
  → `apple`. Cache per domain for a day.
- A hint only orders the buttons. Any address can be a Google account or an Apple ID.

### Upgrades (beta, no billing)

- Account screen **Upgrade** button: Family → Basic, Basic → Frequent Flyer.
- One action, e.g. `App\Actions\ChangeSubscription`, changes `subscription_type`. When billing arrives, Play Billing
  purchase verification calls the same action.
- A family member who upgrades stays in the families they belong to.
- Still to decide: what each tier gets (active watches, family size, email-forward adds), whether a beta upgrade is
  instant or approved, and what happens to an owner's family when they downgrade or delete their account.

### Biometric sign-in

Biometrics unlock something on the phone; the server never sees them. Offer it after the first sign-in and on the
account screen.

| Approach | How | Trade-off |
| --- | --- | --- |
| App lock | Sanctum token in Android Keystore, unlocked by fingerprint or face on open | Simple, no API change; only locks the app |
| Passkey | WebAuthn credential; fingerprint or face approves sign-in; API stores the public key | No password, phishing-proof, syncs across the user's devices; needs Credential Manager from NativePHP and the `assetlinks.json` the design already adds |

### Admin dashboard

Filament on `admin.chippytrip.com`, reachable from anywhere, passkey-only login.

1. Separate Apache virtual host, same app.
2. Passkey-only login (phone QR flow works on borrowed computers); a YubiKey as a second key.
3. `users.is_admin`; `canAccessPanel()` allows only admins; Sanctum API tokens never reach the panel.
4. `php artisan admin:enroll {email}` prints a 15-minute passkey-registration link; also the recovery path.
5. Rate-limited login, short session lifetime, cookies scoped to the admin host.
6. First screens: users (`scopeReal()` hides canary accounts), watches with AeroAPI alert IDs, recent callbacks,
   inbound-email parse errors, beta testers and the waitlist.

### Silent push when an email add creates a watch

The app only syncs watches on open, on push, on pull-to-refresh or after its own changes, so an email-added flight
doesn't appear until the user refreshes. Send a data-only FCM push to the owner when `ParseFlightEmail` creates a watch.
When it ships, update the chippy-canary `appvisible` check
([rmiller335/chippy-canary#13](https://github.com/rmiller335/chippy-canary/issues/13)).

### Beta exit

Decide how beta testers move to billing at launch: a grace period, or keeping their tier free. Belongs with the
"Subscriptions and billing" item in the design's Before public launch list.
