# Phone Members and the Bari App – Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Invite and sign in people by WhatsApp/mobile number, make the app installable as "Bari", and send cost updates as phone notifications.

**Architecture:** Users and invitations get an optional `phone`. A `PhoneMessenger` sends one-off texts (invites, sign-in codes) immediately via WAHA, falling back to the SMS gateway. The Filament login page becomes a two-step "email or phone" form backed by a `LoginCode` service. PWA = static manifest + service worker + render hooks. Web push = `push_subscriptions` + a `WebPushChannel` (minishlink/web-push) fed by `CostNotifier`.

**Tech Stack:** Laravel 13, Filament 5 (Livewire 4), PostgreSQL, minishlink/web-push, vanilla JS service worker.

**Spec:** `docs/superpowers/specs/2026-11-02-phone-members-and-home-screen-app-design.md`

## Global Constraints

- Phones stored as `+<digits>`; local numbers starting with a single `0` get `config('planner.phone_country_code')` (default `880`).
- Sign-in code: 6 digits, 10 minutes, hashed, 5 wrong tries; sends 1/min and 5/hour per number, 20/hour per IP.
- Unknown numbers get the same "We sent a code" screen and no message.
- Code text: `Your Bari code is :code. It expires in 10 minutes.`
- Invite text: `:inviter added you to ":project" on Bari. Tap to join: :url`
- App name everywhere: **Bari** (`APP_NAME`, `MAIL_FROM_NAME`, brand, manifest).
- Every new UI string has a `lang/bn.json` entry (existing test enforces it).
- Failure to send any message never blocks saving a cost or an invite.
- Tests: `php artisan test --compact`; format with `vendor/bin/pint --dirty`.

## Review Focus

1. The same number typed differently (`01712-345678`, `+880 1712 345678`, `০১৭১২৩৪৫৬৭৮`, `008801712345678`) must find the same account and invitation – Task 1 test `test_local_and_international_formats_match_the_same_user`.
2. Inviting a number that already has a pending invitation reuses it and re-sends – Task 3 test `test_inviting_the_same_number_twice_reuses_the_invitation`.
3. WhatsApp and SMS both failing while sending a sign-in code shows the normal code screen (error reported, nothing leaked) – Task 5 test `test_a_failed_send_still_shows_the_code_screen`.
4. A phone-only user must not be shown password fields or a "Forgot password?" path that can't work – Task 6 test `test_phone_only_users_see_no_password_fields`.
5. Push notifications only go to members of the cost's project, never to members of other projects – Task 8 test `test_push_only_reaches_members_of_that_project`.

---

### Task 1: Phone numbers on users and invitations

**Files:**
- Create: `database/migrations/2026_11_03_000100_add_phone_to_users_and_invitations.php`
- Modify: `app/Support/PhoneNumber.php`, `config/planner.php`, `app/Models/User.php`, `app/Models/Invitation.php`, `app/Console/Commands/CreateUser.php`, `database/factories/UserFactory.php`
- Test: `tests/Unit/PhoneNumberTest.php`, `tests/Feature/SignUpTest.php`

**Interfaces:**
- Produces: `PhoneNumber::normalize(?string): string` (now handles local `0…`), `PhoneNumber::isValid(?string): bool` (accepts local), `PhoneNumber::looksLikePhone(string): bool` (no `@`), `PhoneNumber::mask(string): string` (`+880 17•• •••678`); `User::findByLogin(string $emailOrPhone): ?User`; `User::hasPassword(): bool`; columns `users.phone`, `users.phone_verified_at`, `project_invitations.phone`, `project_invitations.locale`; `Invitation::contact(): string`; `UserFactory::phoneOnly()` state.

- [ ] **Step 1: Tests.** `PhoneNumberTest`: `normalize('01712-345678') === '+8801712345678'`, Bangla digits, `00880…`, `+880 …`; `isValid('01712345678')` true, `isValid('12')` false, `isValid('abc')` false; `mask('+8801712345678') === '+880 17•• •••678'`. Feature `test_local_and_international_formats_match_the_same_user`: user with phone `+8801712345678` is found by `User::findByLogin` for all four formats in Review Focus 1; email lookup is case-insensitive. `test_the_command_creates_a_phone_only_user`: `user:create --name=Rina --phone=01712345678 --project=…` → user with phone, null email, verified phone; neither `--email` nor `--phone` → fails.
- [ ] **Step 2:** Run, see them fail.
- [ ] **Step 3: Migration.** users: `email` nullable, `password` nullable, add `phone` string(20) nullable unique, `phone_verified_at` timestamp nullable. project_invitations: `email` nullable, add `phone` string(20) nullable indexed, `locale` string(5) default `en`.
- [ ] **Step 4: Code.** `config/planner.php` `'phone_country_code' => env('PHONE_COUNTRY_CODE', '880')`. PhoneNumber: after Bangla-digit conversion, a value starting with exactly one `0` → country code + digits without that `0`. User: fillable `phone`, cast `phone_verified_at`; saving hook normalises phone and lowercases email. Invitation creating hook: normalise phone, lowercase email when present. `CreateUser`: `--phone` option, email optional when phone given, sets `phone_verified_at`.
- [ ] **Step 5:** Tests pass (whole suite). **Step 6:** Commit `feat: phone numbers on users and invitations`.

### Task 2: PhoneMessenger – send one text now

**Files:**
- Create: `app/Support/PhoneMessenger.php`
- Test: `tests/Feature/PhoneMessengerTest.php`

**Interfaces:**
- Consumes: `App\Services\Waha::sendText`, `App\Services\Sms\SmsSender::send`, `NotificationChannel::isConfigured()`.
- Produces: `PhoneMessenger::isAvailable(): bool` (WhatsApp or SMS configured); `PhoneMessenger::send(string $phone, string $text): ?NotificationChannel` – tries WhatsApp, then SMS; returns the channel that worked, `null` if none; reports exceptions, never throws.

- [ ] **Step 1: Tests** with `Http::fake`: WhatsApp ok → returns `WhatsApp`, no SMS request; WAHA 500 → SMS sent, returns `Sms`; both fail → `null`, no exception; nothing configured → `isAvailable()` false and `send()` null.
- [ ] **Step 2:** Fail. **Step 3:** Implement. **Step 4:** Pass. **Step 5:** Commit `feat: send a one-off text by WhatsApp with SMS fallback`.

### Task 3: Invite by WhatsApp number

**Files:**
- Modify: `app/Actions/InviteMember.php`, `app/Filament/Resources/Memberships/Pages/ManageMemberships.php`, `app/Filament/Widgets/PendingInvitations.php`, `lang/bn.json`
- Test: `tests/Feature/PhoneInviteTest.php`

**Interfaces:**
- Consumes: Task 1 `PhoneNumber`, `User::findByLogin`; Task 2 `PhoneMessenger::send`.
- Produces: `InviteMember::handle(Project $project, string $contact, ProjectRole $role, User $invitedBy, string $locale = 'en', bool $sendCostUpdates = false): array{status: 'added'|'already'|'invited', invitation?: Invitation, user?: User, sentVia?: ?NotificationChannel}`.

- [ ] **Step 1: Tests.** Phone of a new person → invitation with phone + locale, WhatsApp text equals the invite text (inviter name, project, url) in the chosen locale, `sentVia` WhatsApp. Existing user's phone (local format) → `added`. `test_inviting_the_same_number_twice_reuses_the_invitation` → one invitation, two sends. `sendCostUpdates` → a `NotificationRecipient` (WhatsApp, that number, that locale) exists once. Invalid input (`hello`) → validation error from the Livewire action. Both channels down → still `invited`, `sentVia` null. Email path unchanged (existing tests stay green).
- [ ] **Step 2:** Fail.
- [ ] **Step 3: Implement.** `InviteMember`: branch on `PhoneNumber::looksLikePhone`. Form: one `contact` field labelled "Email or WhatsApp number" (validation: email or valid phone), `role`, and – visible when the value looks like a phone (`live(onBlur: true)`) – `locale` toggle and `send_cost_updates` toggle ("Also send them cost updates on WhatsApp"). The link modal's description says "We sent the link on WhatsApp." / "by SMS" / "We couldn't send it – send this link yourself:" / the email text. Widget column shows `Invitation::contact()`.
- [ ] **Step 4:** Pass. **Step 5:** Commit `feat: invite people by WhatsApp number`.

### Task 4: Join with a phone invitation

**Files:**
- Modify: `app/Http/Controllers/InvitationController.php`, `app/Filament/Pages/Auth/Register.php`, `lang/bn.json`
- Test: `tests/Feature/PhoneJoinTest.php`

**Interfaces:**
- Consumes: Task 1 columns, `User::findByLogin`.
- Produces: guests with a phone invitation land on `/register?invitation=…` showing only name + language.

- [ ] **Step 1: Tests.** Guest opens a phone invitation → registration page shows the masked number, no email/password fields. Submitting name `Rina`, locale `bn` → user with that phone, `phone_verified_at` set, null email/password, member with the invited role, invitation accepted, authenticated, redirected to the project. Guest whose phone already has an account → redirected to login. A used phone invitation → rejected.
- [ ] **Step 2:** Fail.
- [ ] **Step 3: Implement.** Controller: existing-account check uses `User::findByLogin($invitation->contact())`. Register: when the invitation has a phone, `form()` has `name` + `locale` (default invitation locale) only; `register()` creates the user, accepts the invitation, `Auth::login($user, remember: true)`, regenerates the session, redirects to the project URL.
- [ ] **Step 4:** Pass. **Step 5:** Commit `feat: join a project from a WhatsApp invitation`.

### Task 5: Sign in with a code on WhatsApp

**Files:**
- Create: `app/Support/LoginCode.php`
- Modify: `app/Filament/Pages/Auth/Login.php`, `lang/bn.json`
- Test: `tests/Feature/PhoneLoginTest.php`

**Interfaces:**
- Consumes: Task 1 `User::findByLogin`, `PhoneNumber::mask`; Task 2 `PhoneMessenger`.
- Produces: `LoginCode::send(string $phone, ?string $ip = null): void` (applies limits; silent for unknown numbers; text in the user's locale); `LoginCode::verify(string $phone, string $code): ?User`; `LoginCode::secondsUntilResend(string $phone): int`.

- [ ] **Step 1: Tests.** Known number → code text sent (regex `/Your Bari code is \d{6}\. It expires in 10 minutes\./`); entering it signs in with remember cookie. Wrong code → error "That code isn't right."; after 5 wrong tries the right code fails too. Code after 10 minutes (`travel(11)->minutes()`) fails. Second send within 60s → no second message. Unknown number → same code screen, no HTTP request. `test_a_failed_send_still_shows_the_code_screen` (WAHA and SMS 500). Nothing configured → phone option shows "Sign-in by phone isn't available right now. Use your email, or ask the project owner." Email + password still works.
- [ ] **Step 2:** Fail.
- [ ] **Step 3: Implement.** `LoginCode`: cache key per normalised phone holding `hash` + `attempts` (TTL 10 min); `RateLimiter` keys `login-code:{phone}` (1/60s, 5/3600s) and `login-code-ip:{ip}` (20/3600s). Login page: replace the email field with `login` ("Email or phone number"); `password` visible for email input; actions "Send code" → `codeSentTo` state + `code` field (one-time-code autocomplete, numeric, 6) + "Sign in" + "Send a new code" (disabled while `secondsUntilResend > 0`, polled countdown) + "Use a different number". Email path calls the parent `authenticate()` with `email` mapped from `login`.
- [ ] **Step 4:** Pass. **Step 5:** Commit `feat: sign in with a code on WhatsApp`.

### Task 6: Phone number in Profile

**Files:**
- Create: `app/Filament/Pages/Auth/EditProfile.php`
- Modify: `app/Providers/Filament/AppPanelProvider.php` (`->profile(EditProfile::class, isSimple: false)`), `lang/bn.json`
- Test: `tests/Feature/ProfilePhoneTest.php`

**Interfaces:**
- Consumes: Task 5 `LoginCode` (a separate `purpose` key `profile` so codes don't mix: `LoginCode::send($phone, purpose: 'profile')`, `LoginCode::verifyCode($phone, $code, purpose: 'profile'): bool` – add these parameters in this task).

- [ ] **Step 1: Tests.** Email user adds phone → "Send code" → entering the code saves phone + `phone_verified_at`; wrong code doesn't save. Phone taken by another user → validation error. `test_phone_only_users_see_no_password_fields`. Clearing email is allowed only when a confirmed phone exists.
- [ ] **Step 2:** Fail. **Step 3:** Implement (phone field read-only with a "Change number" action modal: number → send → code → save). **Step 4:** Pass. **Step 5:** Commit `feat: add a phone number in Profile`.

### Task 7: Bari – name, installable app, offline page

**Files:**
- Create: `public/manifest.webmanifest`, `public/sw.js`, `public/offline.html`, `public/icons/icon.svg`, `public/icons/icon-192.png`, `public/icons/icon-512.png`, `public/icons/icon-maskable-512.png`, `public/icons/apple-touch-icon.png`, `resources/views/filament/pwa-head.blade.php`, `resources/views/filament/pwa-install.blade.php`
- Modify: `app/Providers/Filament/AppPanelProvider.php` (brand "Bari", `HEAD_END` + `BODY_END` render hooks, user-menu "Install app"), `docker-compose.yml`, `.env.example`, `lang/bn.json`
- Test: `tests/Feature/AppShellTest.php`

**Interfaces:**
- Produces: `public/sw.js` with `self.addEventListener('push'|'notificationclick')` stubs ready for Task 8; global JS `window.bari.install()`.

- [ ] **Step 1: Tests.** `/manifest.webmanifest` 200, JSON `name === 'Bari'`, `display === 'standalone'`, four icons; `/sw.js` 200 and JavaScript; login page HTML contains `rel="manifest"` and `apple-touch-icon`; brand shows "Bari".
- [ ] **Step 2:** Fail.
- [ ] **Step 3: Implement.** Icons: house mark from `public/favicon.svg` style on brand emerald (`#047857`), rendered to PNG with headless Chrome (scratch `shoot.mjs`). SW: precache `/offline.html`; cache-first for `/build/` and `/fonts/`; network-first navigations with offline fallback; `skipWaiting` + `clients.claim`. Offline page bilingual, inline styles. Install: listen for `beforeinstallprompt`, show the user-menu item only then; iOS Safari not standalone → dismissible tip on the dashboard (localStorage key `bari-ios-tip-dismissed`, wrapped in try/catch).
- [ ] **Step 4:** Pass. **Step 5:** Commit `feat: Bari home-screen app`.

### Task 8: App notifications (web push)

**Files:**
- Create: migration `2026_11_03_000200_create_push_subscriptions_table.php`, `app/Models/PushSubscription.php`, `app/Support/VapidKeys.php`, `app/Notifications/Channels/WebPushChannel.php`, `app/Http/Controllers/PushSubscriptionController.php`, `resources/views/filament/push-toggle.blade.php`
- Modify: `composer.json` (`minishlink/web-push`), `routes/web.php`, `app/Notifications/CostChanged.php` (`toWebPush`), `app/Support/CostNotifier.php`, `app/Models/User.php` (`pushSubscriptions()`), `public/sw.js`, `app/Providers/Filament/AppPanelProvider.php` (user-menu item), `lang/bn.json`
- Test: `tests/Feature/WebPushTest.php`

**Interfaces:**
- Produces: `POST /push-subscriptions` `{endpoint, keys:{p256dh, auth}}` → 204; `DELETE /push-subscriptions` `{endpoint}` → 204 (both `auth` middleware, CSRF); `VapidKeys::get(): array{publicKey: string, privateKey: string}`; `CostChanged::toWebPush(): array{title: string, body: string, url: string}`.

- [ ] **Step 1: Tests.** Subscribe/unsubscribe store and delete for the signed-in user; guests 401/redirect. Adding a cost notifies other members with a subscription via `WebPushChannel` (Notification fake), not the actor. `test_push_only_reaches_members_of_that_project`. Payload in the member's locale, title = project name, url = Spent page. Channel deletes a subscription when the push service returns 410 (mock `WebPush` returning an expired `MessageSentReport`). `VapidKeys::get()` generates once into `storage/app/private/vapid.json` and returns the same keys after; env values win when set.
- [ ] **Step 2:** Fail.
- [ ] **Step 3: Implement.** `CostNotifier::send` also notifies `$project->users()->whereKeyNot(actor)->whereHas('pushSubscriptions')` with `->locale($user->locale)`, using the same snapshot, inside the same try/catch. SW: `push` shows a notification (icon, badge, data.url); `notificationclick` focuses an open window or opens the url. Toggle: user-menu item runs JS that checks `Notification.permission`, `PushManager`, subscribes with the public key (exposed via a `<meta name="vapid-public-key">`), posts to the route; hidden when push isn't supported (iOS not installed); blocked → notification explaining phone settings.
- [ ] **Step 4:** Pass. **Step 5:** Commit `feat: app notifications for cost updates`.

### Task 9: Docs, screenshots, deploy

**Files:**
- Modify: `docs/handbook.md`, `README.md`

- [ ] **Step 1:** Handbook: inviting by phone (2.2), phone sign-in depends on WAHA/SMS and what to do when WAHA is down (Part 4 row), installing the app and turning on notifications (new 2.7), `user:create --phone`.
- [ ] **Step 2:** Full suite + Pint. Screenshots (desktop, phone, dark, Bangla) of invite modal, join page, phone sign-in both steps, profile phone, manifest install (phone viewport). Fix what looks wrong.
- [ ] **Step 3:** Local Docker image build succeeds (web-push needs `gmp` or `bcmath` – already installed – check `composer install` in the vendor stage).
- [ ] **Step 4:** Commit `docs: phone members and the Bari app`, push.
