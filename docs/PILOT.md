# Pilot School — St. Mary's LP School (Ernakulam)

## Adoption metrics to track

| Metric | Target | How to measure |
|--------|--------|----------------|
| Parent adoption | 70%+ in 2 weeks | `users` with role `parent` who have `notice_reads` |
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

## Onboarding checklist for real pilot

1. Run `php artisan migrate --seed` on staging
2. Replace pilot phones with real school contacts
3. Import class list via admin (CSV upload — future)
4. Send SMS invite to parents with magic link to first notice
5. Disable WhatsApp groups for official notices (school policy)
6. Review adoption metrics weekly in first month
