# EduBridge

Official school communication PWA for Kerala schools — notices, calendar, feedback, SMC coordination, and more.

## Stack

- Laravel 13 + Vue 3 PWA (Vite, Tailwind, vue-i18n)
- Phone OTP authentication (no passwords)
- WhatsApp bridge for urgent notice alerts (log driver in development)

## Requirements

- **PHP 8.5+** with `pdo_sqlite` / `pdo_mysql`, `mbstring`, `fileinfo`, `gd`
- **Node.js 20+** (Vite 6); older Node versions hang or fail during `npm run build`
- Composer 2, and MySQL 8 or SQLite

On Laragon, the default `php` / `node` on PATH may be old. Use the bundled versions
(adjust to your install), for example in Git Bash:

```bash
export PATH="/d/Apps/laragon/bin/php/php-8.5.7-nts-Win32-vs17-x64:/d/Apps/laragon/bin/nodejs/node-v22:$PATH"
php -v && node -v
```

## Quick start (local)

```bash
composer install
cp .env.example .env          # set DB_* (or keep DB_CONNECTION=sqlite)
php artisan key:generate
php artisan migrate --seed    # pilot school + demo accounts (skipped when APP_ENV=production)
npm install
npm run build                 # or `npm run dev` for hot reload
```

Run the app. You need the web server, a queue worker (WhatsApp alerts, event reminders) and the scheduler (scheduled notices, reminders):

```bash
composer dev                  # server + queue + logs + vite, all in one terminal
# or separately:
php artisan serve
php artisan queue:work
php artisan schedule:work
```

Open http://127.0.0.1:8000 and sign in with a demo phone number below. In development the OTP
is not texted: it is written to `storage/logs/laravel.log` (search for `SMS (log driver)`).
To skip that, set `EDUBRIDGE_OTP_DEV_CODE=123456` in `.env` (honoured only when `APP_ENV=local`).

If the page is blank after a `npm run dev` session, delete `public/hot`.

Run the tests: `php artisan test`

## Pilot school (seeded)

| Role | Phone |
|------|-------|
| **Platform Admin (Super Admin)** | **9900000001** |
| School Admin | 9876543210 |
| Parent | 9123456789 |
| Teacher | 9876501234 |
| Alumni | 9988776655 |

**Platform admin console:** `/platform/login`  
**School registration:** `/school/register`  
**Demo magic link notice:** `/n/demo123abc`

## Features

- **MVP:** Notices, calendar, feedback, SMC board, WhatsApp urgent alerts
- **School operations:** student health profiles, emergency contacts and authorised pickup; attendance marking with WhatsApp absence alerts and monthly reports; daily log / incident reports (optionally shared with parents); daily and weekly checklists with completion reports; staff-initiated messages to parents
- **Year 1:** Student portal (homework, attendance, timetable), teacher workspace
- **Year 1–2:** Online examinations with auto-grading
- **Year 2:** School transport (routes, trips, boarding logs)
- **Year 2+:** Alumni portal (events, jobs, mentorship, donations)

## API

All routes under `/api` — session auth via OTP login.

## Production deploy (GitHub Actions)

Pushes to `main` run tests and build on GitHub, then deploy on your **self-hosted runner** (apstrix). No inbound SSH from GitHub is required.

### One-time server setup (aaPanel / multiple projects)

EduBridge uses its **own folder**. Other sites on apstrix are not changed.

Typical aaPanel layout:

```
/www/wwwroot/
├── other-project.com/     ← your existing sites (leave as-is)
├── another-app.com/
└── edubridge.example.com/ ← EduBridge only (new)
    ├── releases/          ← each deploy (git SHA)
    ├── shared/
    │   ├── .env           ← production env (persists across deploys)
    │   └── storage/       ← uploads, logs, cache
    └── current → releases/<sha>/   ← symlink, web root points here
```

**1. Create a site in aaPanel** for EduBridge (e.g. `edubridge.example.com`).

**2. Pick a dedicated path** (match the site folder aaPanel created, or choose your own):

```bash
export APP_DIR=/www/wwwroot/edubridge.example.com
```

**3. Run init** (from a clone of this repo, or copy `scripts/server-init.sh` to the server):

```bash
bash scripts/server-init.sh
```

**4. Production `.env`** — only for EduBridge:

```bash
nano $APP_DIR/shared/.env
# copy from .env.example, set DB, APP_URL, etc.
```

**5. aaPanel site settings** — set document root to:

```
/www/wwwroot/edubridge.example.com/current/public
```

(`current` does not exist until the first deploy; you can point it after deploy #1, or create a placeholder.)

**6. GitHub secret** `PRODUCTION_PATH` = same path, e.g. `/www/wwwroot/edubridge.example.com`

Other GitHub repos / projects on the same server: use a **separate** `APP_DIR` and (if needed) a **separate** self-hosted runner under `/opt/github-runner-<project>`.

### Install self-hosted runner (apstrix)

1. Open [New self-hosted runner](https://github.com/gofreefolk/edubridge/settings/actions/runners/new)
2. Copy the registration token
3. On the server:

```bash
REGISTRATION_TOKEN=YOUR_TOKEN \
  RUNNER_DIR=/opt/github-runner-edubridge \
  bash scripts/install-github-runner.sh

cd /opt/github-runner-edubridge
sudo ./svc.sh install
sudo ./svc.sh start
```

The runner must have labels: `self-hosted`, `linux`, `edubridge`.

### GitHub secret

| Secret | Description |
|--------|-------------|
| `PRODUCTION_PATH` | This project’s app root only, e.g. `/www/wwwroot/edubridge.example.com` |

Ensure PHP 8.5+ CLI is on the server (`php -v`). The runner user must be able to write to `PRODUCTION_PATH`.

### How a deploy runs

`scripts/deploy-activate.sh` unpacks the release, links the shared `.env` and `storage`,
puts the live site into maintenance, runs migrations and warms caches **on the new
release**, then atomically switches `current` and brings the site back up. If any step
fails, `current` keeps pointing at the previous release and the site is brought back up.

### Queue worker and scheduler (required)

Urgent-notice WhatsApp alerts and event reminders run on the queue, and scheduled
notices are published by the scheduler. Without these, those features silently do nothing.

Cron (as the web/deploy user):

```
* * * * * cd /www/wwwroot/edubridge.example.com/current && php artisan schedule:run >> /dev/null 2>&1
```

Supervisor (aaPanel → Supervisor, or `/etc/supervisor/conf.d/edubridge-worker.conf`):

```ini
[program:edubridge-worker]
command=php /www/wwwroot/edubridge.example.com/current/artisan queue:work --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www
stopwaitsecs=3600
stdout_logfile=/www/wwwroot/edubridge.example.com/shared/storage/logs/worker.log
```

Each deploy runs `queue:restart`, so the worker picks up new code automatically.

### Production `.env` essentials

| Key | Value |
|-----|-------|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `QUEUE_CONNECTION` | `database` (or `redis`) |
| `EDUBRIDGE_SMS_DRIVER` | `http`. The `log` driver cannot deliver OTPs, so nobody can log in |
| `EDUBRIDGE_SMS_WEBHOOK_URL` / `_TOKEN` | Your SMS gateway adapter; receives `{"phone": "+91…", "message": "…"}` |
| `EDUBRIDGE_WHATSAPP_DRIVER` / `_WEBHOOK_URL` | `http` and your WhatsApp bridge URL |
| `EDUBRIDGE_OTP_DEV_CODE` | leave empty |
| `APP_URL` | The public `https://` address. Payment return links, the Razorpay webhook URL and links in fee reminders are built from it |
| `APP_KEY` | Never rotate once schools have saved Razorpay keys: their secrets are encrypted with it |

### Online fee payment (Razorpay)

Each school uses its own Razorpay account, so fees go straight to the school. The school
admin sets it up in **Fees → Fee setup → Online payment**:

1. In Razorpay Dashboard → Account & Settings → API Keys, generate keys and paste the Key ID and Key Secret.
2. In Razorpay Dashboard → Account & Settings → Webhooks, add the webhook URL shown on the
   setup page (`/api/webhooks/razorpay/{school id}`), pick a secret, and tick
   `payment_link.paid`, `payment_link.cancelled` and `payment_link.expired`.
3. Paste the same webhook secret, tick **Let parents pay online** and save. The keys are checked with Razorpay before saving.

Parents then see **Pay online** on their child's fees, and fee reminders include a link.
Each payment is recorded and receipted automatically, from either the browser return or
the webhook, and is recorded only once.

Do not run `db:seed` in production: the pilot seeder creates a super admin whose phone number is published in this README (the seeder refuses when `APP_ENV=production`).

## License

MIT
