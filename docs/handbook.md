# House Cost Planner – Handbook

How to put the app online at **bari.imrankhanshuvo.com** and keep it running.

- [Part 1 – Deploy](#part-1--deploy) (once)
- [Part 2 – Run it](#part-2--run-it) (everyday tasks)
- [Part 3 – Backups and restore](#part-3--backups-and-restore)
- [Part 4 – When something goes wrong](#part-4--when-something-goes-wrong)
- [Checklists](#checklists)

Most commands below are typed in Coolify: open the stack → **Terminal** → choose the container
named in the step (**app**, **backup**…) → type the command.

---

## What runs where

Everything is one Coolify stack, defined in `docker-compose.yml`:

| Service | What it does | Address |
|---|---|---|
| `app` | the website; updates the database tables on every deploy | `https://bari.imrankhanshuvo.com` |
| `worker` | sends WhatsApp, SMS and email messages in the background | – |
| `db` | PostgreSQL database | – |
| `waha` | WhatsApp gateway; you scan a QR code once | `https://waha.imrankhanshuvo.com` |
| `backup` | copies the database and bill photos every night, keeps 14 days | – |

Data is kept in four volumes that survive redeploys:

| Volume | Contents |
|---|---|
| `db-data` | the database: projects, costs, people |
| `app-files` | uploaded bill photos and receipts |
| `waha-sessions` | the WhatsApp login (lose it and you scan the QR again) |
| `backups` | the nightly backups |

---

## Part 1 – Deploy

### 1.1 DNS

At the DNS provider for imrankhanshuvo.com, add two **A records** pointing at your Coolify
server's IP address:

| Name | Type | Value |
|---|---|---|
| `bari` | A | server IP |
| `waha` | A | server IP |

On Cloudflare, set both to **DNS only** (grey cloud) so Coolify can get the HTTPS certificates.
You can turn the orange cloud on later with SSL mode **Full (strict)**.

### 1.2 Create the stack

1. Push this project to a Git repository (GitHub, GitLab…). A private repository is fine.
2. Coolify → your project → **+ New** → the repository. For a private GitHub repository, connect
   it through Coolify's GitHub App.
3. **Build pack:** Docker Compose. **Docker Compose location:** `/docker-compose.yml`.
4. **Domains:**
   - service **app** → `https://bari.imrankhanshuvo.com:8080`
   - service **waha** → `https://waha.imrankhanshuvo.com:3000`

   The `:8080` / `:3000` only tells Coolify which port inside the container to use. People
   still open the normal address.
5. **Environment variables** – fill in only what you use:

   | Variable | Value |
   |---|---|
   | `MAIL_MAILER` | `smtp` (leave `log` until you have email set up) |
   | `MAIL_USERNAME`, `MAIL_PASSWORD` | from your mail provider, e.g. Brevo (free: 300 emails/day) |
   | `MAIL_FROM_ADDRESS` | `bari@imrankhanshuvo.com` – verify the domain with your mail provider first |
   | `SMS_GATE_USERNAME`, `SMS_GATE_PASSWORD` | see 1.5 |

   Coolify generates everything named `SERVICE_…` (app key, database password, WhatsApp key,
   WAHA dashboard password). **Never change these.** A new `APP_KEY` signs everyone out, and a
   new database password locks the app out of its own database.
6. **Deploy.** The first deploy takes a few minutes. When **app** shows *healthy*, open
   `https://bari.imrankhanshuvo.com`.

### 1.3 Your first account

Terminal → **app**:

```bash
php artisan user:create
```

It asks for name, email and password (leave the password empty to get a random one). Sign in,
create your house project, then invite the others (see 2.2).

### 1.4 WhatsApp

You need a spare SIM with WhatsApp on a phone. Don't use your personal number: WAHA is not an
official WhatsApp product, so the number could be blocked.

1. Open `https://waha.imrankhanshuvo.com/dashboard`. User `admin`; the password is
   `SERVICE_PASSWORD_WAHADASHBOARD` in Coolify's environment variables.
2. When it asks for the server's API key, use `SERVICE_PASSWORD_WAHAKEY` from the same list.
3. Start the session called **default**. A QR code appears.
4. On the spare phone: WhatsApp → **Settings → Linked devices → Link a device**, and scan it.

Nothing else to set: the app already knows how to reach WAHA inside the stack.

### 1.5 SMS (optional)

1. On an Android phone with a SIM, install **SMS Gateway for Android** (Google Play, or
   [github.com/capcom6/android-sms-gateway](https://github.com/capcom6/android-sms-gateway)).
2. Turn on **Cloud server**, tap **Start service**, keep the default **Sign up**.
3. Copy the username and password it shows into `SMS_GATE_USERNAME` and `SMS_GATE_PASSWORD`
   in Coolify, then **Redeploy**.

Each SMS costs your normal SMS rate. The message text passes through the gateway's cloud server
on the way to the phone. The same spare phone can run WhatsApp and this app.

### 1.6 Who gets messages

In the app: **Project settings → Notifications → Add person**. Choose WhatsApp, SMS or Email,
type the number with country code (e.g. `+8801712345678`) or the email, and pick English or
বাংলা. Save, then press **Send a test message**. It sends straight away and tells you what went
wrong if something fails.

### 1.7 Get told when something breaks

- **Coolify → Settings → Notifications:** add your email (or Telegram/Discord) and turn on
  failed deployments and container status changes.
- Optional: a free uptime monitor (UptimeRobot, Better Stack) on
  `https://bari.imrankhanshuvo.com/up`. It answers 200 when the app is up.

---

## Part 2 – Run it

### 2.1 Update the app

Push to the repository, then press **Redeploy** in Coolify (or turn on **Auto Deploy** so every
push deploys). Database changes are applied automatically when the app starts. The site is
unavailable for about a minute while the containers restart. If the build fails, the old
version simply keeps running.

To undo a bad update, revert the commit in Git and push again. If that update changed the
database and things look wrong, restore last night's backup (Part 3).

### 2.2 People

**Inviting** (in the app, as an Owner): **Team → Invite someone**. They get an email with a
link. If email isn't set up yet, press **Get link** next to the invitation in the list and send
the link on WhatsApp. Only invited people can create an account.

**Change role or remove:** **Team** → the person's row → **Change role** or **Remove**.
Removing someone takes them out of that project; their account stays.

**Forgot password:** the **Forgot password?** link on the sign-in page works once email is set
up. Otherwise, Terminal → **app**:

```bash
php artisan user:password karim@example.com             # asks for the new password
php artisan user:password karim@example.com --password=random
```

This signs them out on all devices.

**Add someone straight into a project** without an invitation:

```bash
php artisan user:create --name="Karim" --email=karim@example.com --password=random --project="Our new house" --role=editor --locale=bn
```

Roles: `owner` (everything, including settings and team), `editor` (add and edit costs),
`viewer` (can only look).

### 2.3 Change a setting

Environment variables → change the value → **Redeploy**. Settings are only read when the app
starts, so a change does nothing until you redeploy.

| To… | Set |
|---|---|
| turn on email | `MAIL_MAILER=smtp` plus `MAIL_USERNAME`, `MAIL_PASSWORD` (and `MAIL_HOST` if not Brevo) |
| let anyone sign up | `OPEN_REGISTRATION=true` (not recommended) |
| keep backups longer | `BACKUP_KEEP_DAYS=30` |
| back up at another time | `BACKUP_HOUR_UTC=…` (UTC; Bangladesh is UTC+6, so 21 = 03:00) |

### 2.4 Messages

Messages are sent by the **worker** a few seconds after a cost is saved. A failed message is
tried 3 times (after 30 seconds, then 2 minutes) and never stops a cost from being saved.

Terminal → **app**:

```bash
php artisan queue:failed        # messages that gave up, with the reason
php artisan queue:retry all     # try them again (after fixing the cause)
php artisan queue:flush         # forget them
```

### 2.5 The WhatsApp phone

- Keep the spare phone switched on and online now and then. Linked devices stop working after
  about two weeks with the phone offline.
- If WhatsApp messages start failing: open the WAHA dashboard. If the **default** session isn't
  *WORKING*, start it and scan the QR again.
- If the number gets blocked: put a new SIM in, log out the session in the WAHA dashboard, and
  scan with the new number. Nothing changes in the app.

### 2.6 Take the site offline for a moment

Terminal → **app**: `php artisan down` shows a "back soon" page; `php artisan up` brings it
back. A redeploy also brings it back.

---

## Part 3 – Backups and restore

### What is backed up

The **backup** service copies the database and all uploaded bill photos:

- every night at 03:00 Bangladesh time,
- and once every time the stack is deployed.

Backups older than 14 days are deleted. They are kept in the `backups` volume on the **same
server**, so they don't protect you if the server itself is lost. Copy them off the server once
a month (see below).

The WhatsApp login and Coolify's own settings are not in these backups. If they're lost you
only scan the QR again and fill in the few environment variables.

### See the backups

Terminal → **backup**:

```bash
ls -lh /backups
```

You'll see pairs like `db_2026-11-02_210000.dump` and `files_2026-11-02_210000.tar.gz`. The
times are UTC.

### Back up right now

Do this before anything risky (a big update, deleting a lot of data). Terminal → **backup**:

```bash
backup-now
```

### Restore

Terminal → **backup**:

```bash
backup-restore                       # lists the backups you can go back to
backup-restore 2026-11-02_210000     # goes back to that one
```

It first saves the current state as a new backup, so a restore can itself be undone. Then it
puts back the database and the bill photos. Everything added after that backup is gone, so tell
the team. Do it when nobody is adding costs.

### Copy backups off the server (monthly)

On your Mac, in Terminal (replace `SERVER` with your server's address):

```bash
ssh root@SERVER 'docker ps --format "{{.Names}}" | grep "^backup-"'
# prints the container's name, e.g. backup-k8s0gw4c…  – use it below
ssh root@SERVER 'docker exec backup-k8s0gw4c… tar -cz -C /backups .' > bari-backups-$(date +%F).tar.gz
```

Keep the file somewhere safe (Google Drive, an external disk). Your VPS provider's own snapshot
feature (Hetzner, DigitalOcean… usually about 20% of the server price) is a good extra layer.

### Move to a new server (or recover from a lost one)

1. Deploy the stack on the new server (Part 1). Copy the `SERVICE_…` values from the old
   server's environment variables if you still have them. If not, everyone signs in again.
2. Copy a backup into the new backup container. On your Mac:

   ```bash
   ssh root@NEWSERVER 'docker ps --format "{{.Names}}" | grep "^backup-"'
   cat bari-backups-2026-12-01.tar.gz | ssh root@NEWSERVER 'docker exec -i backup-… tar -xz -C /backups'
   ```

3. Terminal → **backup** → `backup-restore` (pick the newest) → `backup-restore <that one>`.
4. Point the DNS records at the new server, then scan the WhatsApp QR again (1.4).

---

## Part 4 – When something goes wrong

Start with the **Logs** of the service involved (Coolify → the stack → the service → Logs).

| Problem | What to do |
|---|---|
| Site shows "No available server" | The domain is missing `:8080` / `:3000` (1.2), or **app** isn't healthy yet – check its logs |
| Site shows a 500 error | Logs of **app**. Usually a database problem: is **db** running? |
| Deploy failed | The deployment log in Coolify. Fix, push, redeploy – if the build failed, the old version is still running |
| Test message works, real messages don't arrive | Logs of **worker**; `php artisan queue:failed` (2.4) |
| WhatsApp fails with 422 | The **default** session isn't running – scan the QR again (2.5) |
| WhatsApp fails with 401 | `SERVICE_PASSWORD_WAHAKEY` was changed – redeploy so both services use the same value |
| SMS fails | Is the gateway phone on, online and the app's service started? Check `SMS_GATE_*` |
| Emails never arrive | `MAIL_*` settings; the sender address must be verified with your mail provider. Check spam |
| Everyone signed out, or 500 errors right after changing variables | A `SERVICE_…` value was changed – put the old value back and redeploy |
| Someone can't open the sign-up page | Sign-up is invite-only: invite them (2.2). A used link can't be reused – send a new invitation |
| Disk full | `ls -lh /backups` in **backup**; lower `BACKUP_KEEP_DAYS`. Coolify → Server → Docker cleanup |
| Data deleted by mistake | Restore last night's backup (Part 3) – note that it rolls back *everything* |

---

## Checklists

**Monthly (5 minutes)**

- [ ] Open the app, add and delete a test cost – did the messages arrive?
- [ ] WAHA dashboard: session **default** is *WORKING*
- [ ] Terminal → **backup** → `ls -lh /backups`: there's a backup from last night
- [ ] Copy the backups to your Mac (Part 3)
- [ ] Coolify: server disk usage below 80%

**Every few months**

- [ ] Practise a restore on a quiet day: `backup-now`, then `backup-restore` the backup you just
      made. Nothing should change.
- [ ] Turn the WhatsApp and SMS phones on and check they're updated and charged
- [ ] Update the app (2.1) so security updates for Laravel and PHP get in

**Never**

- Change `APP_KEY` or any `SERVICE_…` variable
- Delete the `db-data` or `app-files` volume
- Turn on `OPEN_REGISTRATION` unless you mean it
