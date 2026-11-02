# Phone-number members and the "Bari" home-screen app

Date: 2026-11-02 · Status: agreed in chat, awaiting spec review

## Why

The owner wants to add family (starting with his wife) to a project by WhatsApp/mobile number,
without them needing an email address, and wants the app on everyone's phone like a normal app.
Cost updates should be able to reach the team as phone notifications, so WhatsApp (WAHA, an
unofficial gateway on the owner's number) carries less load.

Decided in chat:

- Home-screen app (installable web app), not app-store apps or a native app.
- People added by phone sign in with a **6-digit code on WhatsApp** (SMS as backup). No password.
- **App notifications** (web push) are in.
- Name on the home screen: **Bari**.

Success looks like: the owner types his wife's number in Team → she gets a WhatsApp message →
taps the link → types her name → she's in, with Bari on her home screen and cost alerts on her
phone, without ever touching an email address or password.

---

## Part 1 – Members by phone number

### Data

- `users.email` becomes nullable. New `users.phone` (nullable, unique, stored as `+8801712345678`)
  and `users.phone_verified_at`. Every user has an email, a phone, or both (validated in the app).
- `project_invitations.email` becomes nullable; new `project_invitations.phone` (nullable).
  An invitation has exactly one of the two.

### Phone numbers

`App\Support\PhoneNumber` also accepts local numbers: a number starting with a single `0`
(`01712345678`, `০১৭১২…`) gets the default country code from `config('planner.phone_country_code')`
(default `880`). This also applies to the Notifications list in Project settings.

### Inviting (Team → Invite someone)

- One field: **"Email or WhatsApp number"**. Input containing `@` is an email, otherwise it must be
  a valid phone number, else a validation error.
- New tick box (shown for phone numbers): **"Also send them cost updates on WhatsApp"**. When
  ticked, the number is added to the project's Notifications list (in the language chosen below).
- New language choice (English / বাংলা) for phone invitations, used for the invitation message,
  the new account and the Notifications entry.
- If the email/phone already belongs to a user → added to the project at once (as today).
- Otherwise an invitation is created and, for a phone, sent right away (not queued) by WhatsApp,
  falling back to SMS if WhatsApp fails or isn't set up:
  > Imran added you to "Our new house" on Bari. Tap to join: https://bari…/invitations/{token}
- The existing "copy link" window still appears, so the owner can forward the link himself if
  neither channel worked; it says which channel the message went out on, or that it couldn't be
  sent.
- `InviteMember` handles both kinds; the pending-invitations list shows the phone or the email.

### Joining

- The invitation link works as today: signed-in users accept it directly; a guest with an
  existing account (matching email or phone) is sent to sign in; otherwise the join page opens.
- Join page for a phone invitation: heading *Join "Our new house"*, the number shown and locked,
  fields **Your name** and **Language**, button **Join**. No email, no password.
- **Join** sends a 6-digit code to the invited number (same rules as sign-in codes) and the page
  asks for it. Only then is the account created with `phone_verified_at` set, the invitation
  accepted, and the person signed in with "remember me", landing in the project.
  (Changed after review: the link alone isn't proof – the inviter sees it too and could claim the
  number.)
- Email invitations keep today's sign-up form.
- A used invitation can't be used again (as today).

### Signing in

- The sign-in page has one field: **"Email or phone number"**.
- Email → password field → sign in (as today, including "Forgot password?").
- Phone → **Send code** → the screen changes to "We sent a code to +880 17•• •••678" with a code
  field, **Sign in**, and **Send a new code** (enabled after 60 seconds). Sign-in uses
  "remember me", so people stay signed in on that phone for months.
- Code rules: 6 digits, valid 10 minutes, stored hashed (cache), 5 wrong tries kill it. Sending is
  limited to 1 per minute and 5 per hour per number, and 20 per hour per IP address.
- The screen never reveals whether a number has an account: unknown numbers get the same
  "We sent a code" screen, and no message is sent.
- Codes go out immediately (not queued) by WhatsApp, then SMS if WhatsApp fails. Message:
  `Your Bari code is 482913. It expires in 10 minutes.` (in the user's language).
- If neither WhatsApp nor SMS is set up on the server, the phone option shows
  "Sign-in by phone isn't available right now. Use your email, or ask the project owner."

### Profile

- Profile gets a **Phone number** field. Setting or changing it sends a code to the new number;
  the number is saved only after the code is entered. After that the person can sign in either way.
- Email becomes optional for anyone who has a confirmed phone number (an account can't end up
  with neither).
- Password fields are only shown to people with an email (phone-only people don't need one).

### Command line

`php artisan user:create` gets `--phone=`; an account needs `--email` or `--phone` (or both).
`user:password` stays email-only.

---

## Part 2 – The home-screen app ("Bari")

### Name

The app is called **Bari** everywhere a person sees a name – home-screen icon, browser tab,
sign-in page, emails and WhatsApp/SMS messages – so the icon and the messages match.
(`APP_NAME` and `MAIL_FROM_NAME` in `docker-compose.yml` and `.env.example` become `Bari`.)

### Installable

- `public/manifest.webmanifest`: name and short name **Bari**, `start_url` `/`, `display`
  `standalone`, theme and background colours from the app theme (light), `lang` `en`.
- Icons: a simple house mark on the brand colour – 192px, 512px, a 512px maskable version and a
  180px `apple-touch-icon`; generated from one SVG checked into the repo.
- `<link rel="manifest">`, `theme-color` and Apple web-app meta tags are added to every page via a
  Filament render hook.

### Service worker (`public/sw.js`, scope `/`)

- Caches the built CSS/JS and fonts (cache-first) and an offline page.
- Page loads go to the network; if offline, the offline page shows: "You're offline. Bari will
  load again when you're connected." (English and Bangla).
- No offline data entry.
- Handles push messages and notification taps (Part 3).
- A new version takes over on the next app start; no "update available" prompt.

### Install prompts

- In the user menu: **Install app** – shown only when the browser offers installation
  (Android/desktop Chrome, Edge); uses the browser's own install dialog.
- iPhone/iPad Safari, not yet installed: a small dismissible tip on the overview page:
  "Add Bari to your home screen: tap Share, then Add to Home Screen." Dismissal is remembered on
  that device (browser storage).
- Nothing is shown once the app runs installed.

---

## Part 3 – App notifications (web push)

### Who gets what

- Every team member (owner, editor, viewer) can turn on notifications per device. Once on, they get
  a phone notification whenever a cost in their projects is added, changed, marked paid/unpaid or
  deleted – the same events and wording as the WhatsApp/SMS/email messages (`CostChanged`).
- The person who made the change doesn't get a notification for it.
- Text in each person's own language. Title: the project name. Body: the headline and the
  amount/supplier summary. Tapping it opens the Spent page of that project.
- The Notifications list in Project settings is unchanged (it's for people without an account, or
  for extra channels).

### Turning it on

- User menu: **Turn on notifications** → the browser's permission prompt → saved for this device.
  The item then reads **Turn off notifications** for that device.
- On iPhone this only works once Bari is installed (iOS 16.4+); before that the item is hidden.
- If the person blocked notifications in the browser, a short note explains how to allow them in
  the phone's settings.

### How it works

- `push_subscriptions` table: user, endpoint (unique), keys, user agent, timestamps. Removed when
  the push service reports it gone (404/410) or the user turns it off.
- Sending uses the `minishlink/web-push` library (VAPID) through a custom notification channel,
  queued on the existing worker, like the other channels.
- VAPID keys: taken from `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` if set; otherwise generated once
  on first use and stored in `storage/app/private/vapid.json` (on the `app-files` volume, so they
  survive redeploys and are included in backups). Nothing to set up in Coolify.
- Failure to send never blocks saving a cost (same as the other channels).

---

## Out of scope

App-store apps, offline cost entry, notification preferences per event type, sign-in by email
code, changing someone else's phone number (owners can remove and re-invite instead).

## Testing

- Feature tests: invite by phone (new number, existing user, local `01…` format, invalid input,
  "also send updates" adds a Notifications entry, WhatsApp→SMS fallback, nothing set up);
  joining by phone link (account created, verified, signed in, used link rejected); phone sign-in
  (correct code, wrong code, expiry, 5 tries, rate limits, unknown number gets the same screen and
  no message, no channel configured); profile phone change needs the code; `user:create --phone`.
- Push: subscribe/unsubscribe endpoints, the actor is skipped, payload in the member's language,
  expired subscriptions removed, keys generated once and reused.
- Manifest, service worker and offline page are served with the right content types.
- Every new string has a Bangla entry (existing test).
- Screenshots: invite, join, sign-in by phone, profile – desktop, phone, dark mode, Bangla; plus the
  installed app on a phone-sized screen.

## Docs

The handbook gets: phone sign-in depends on WhatsApp (WAHA) or SMS being connected; what to do
if WAHA is down (email sign-in, or re-scan); app notifications need nothing on the server.
