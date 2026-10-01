# Pilot School — St. Mary's LP School (Ernakulam)

## Adoption metrics to track

| Metric | Target | How to measure |
|--------|--------|----------------|
| Parent adoption | 70%+ in 2 weeks | Admin dashboard → Parent adoption tile (parents who read a notice; also who opened the app, and in the last 7 days) |
| Notice engagement | 3+ notices/week via EduBridge | Count `notices` where `status=published` per week |
| WhatsApp bridge | Urgent notices sent | `notification_logs` where `channel=whatsapp` |
| SMC transparency | 1 meeting/month published | `smc_meetings` with `minutes` filled |
| Feedback usage | Parents using threads vs WhatsApp | Count `feedback_threads` created per month |

## Seeded test accounts

| Role | Phone | OTP |
|------|-------|-----|
| School Admin | 9876543210 | Request via login |
| Parent (Anu's mother) | 9123456789 | Request via login |
| Teacher | 9876501234 | Request via login |
| Alumni | 9988776655 | Request via login |

## Demo URLs

- Home: `/`
- Sample urgent notice (magic link): `/n/demo123abc`
- Exam notice: `/n/exam2025mar`

## Go-live checklist (production)

Merging `dev` → `main` deploys automatically (see README → Production deploy). Before and
after the first real deploy:

1. On the server, in `shared/.env`: `APP_URL` is the public https address,
   `EDUBRIDGE_SMS_DRIVER=http` with the SMS webhook URL/token (OTP login fails otherwise), and
   `EDUBRIDGE_WHATSAPP_DRIVER=http` with the WhatsApp bridge URL.
2. After the deploy, run `php current/artisan migrate:status`. Every migration should show `Ran`.
3. Check the queue worker is running (`supervisorctl status`) and the scheduler cron entry exists (`crontab -l`).
4. Log in with a real phone number to confirm OTP SMS delivery.

## Onboarding checklist for the pilot school

1. Create the school from the platform admin, or approve its registration, and send the admin invite.
2. Admin adds classes, then imports students and parents with the in-app CSV import
   (**Admin → Import parents**, see `docs/CSV_IMPORT.md`).
3. Send parents an SMS invite with the magic link to the first notice.
4. Disable WhatsApp groups for official notices (school policy).
5. If the school collects fees: set up fee heads and amounts, and optionally online payment
   (README → Online fee payment).
6. Review the admin dashboard adoption tile weekly in the first month.
