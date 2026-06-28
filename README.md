# EduBridge

Official school communication PWA for Kerala schools — notices, calendar, feedback, SMC coordination, and more.

## Stack

- Laravel 13 + Vue 3 PWA (Vite, Tailwind, vue-i18n)
- Phone OTP authentication (no passwords)
- WhatsApp bridge for urgent notice alerts (log driver in development)

## Quick start

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

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
- **Year 1:** Student portal (homework, attendance, timetable), teacher workspace
- **Year 1–2:** Online examinations with auto-grading
- **Year 2:** School transport (routes, trips, boarding logs)
- **Year 2+:** Alumni portal (events, jobs, mentorship, donations)

## API

All routes under `/api` — session auth via OTP login.

## Production deploy (GitHub Actions)

Pushes to `main` run tests and build on GitHub, then deploy on your **self-hosted runner** (apstrix). No inbound SSH from GitHub is required.

### One-time server setup

```bash
export APP_DIR=/www/wwwroot/edubridge   # your app root
bash scripts/server-init.sh
# Place production .env at: $APP_DIR/shared/.env
```

Point the web server document root to `{APP_DIR}/current/public`.

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
| `PRODUCTION_PATH` | App root on the server (e.g. `/www/wwwroot/edubridge`) |

Ensure PHP 8.5+ CLI is on the server (`php -v`). The runner user must be able to write to `PRODUCTION_PATH`.

## License

MIT
