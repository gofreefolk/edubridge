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
| School Admin | 9876543210 |
| Parent | 9123456789 |
| Teacher | 9876501234 |
| Alumni | 9988776655 |

**Demo magic link notice:** `/n/demo123abc`

## Features

- **MVP:** Notices, calendar, feedback, SMC board, WhatsApp urgent alerts
- **Year 1:** Student portal (homework, attendance, timetable), teacher workspace
- **Year 1–2:** Online examinations with auto-grading
- **Year 2:** School transport (routes, trips, boarding logs)
- **Year 2+:** Alumni portal (events, jobs, mentorship, donations)

## API

All routes under `/api` — session auth via OTP login.

## License

MIT
