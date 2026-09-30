# EduBridge Roadmap — LittleLives parity

Goal: cover the modules of the LittleLives Basic Package so EduBridge can be sold to
unaided / CBSE schools and preschools, not only aided LP schools.

## Where we are

| LittleLives module | EduBridge | Status |
|---|---|---|
| Student & staff particulars | `SchoolStudentController`, `SchoolStaffController`, `StudentProfileController`, `student_contacts` | Done |
| Web attendance | `AttendanceController` + absence WhatsApp alerts + monthly report | Done |
| Centre log | `CentreLogController` | Done |
| Centre checklist & reports | `ChecklistController` | Done |
| Events & bulletin board | Notices, calendar, event reminders | Done |
| 2-way communications | Feedback threads, staff messages, WhatsApp bridge | Done |
| Parents portal | `ParentDashboardController` / `MyChildPage` | Partial — grows with each phase below |
| Management reports & dashboard | Per-module reports only | **Phase 1** |
| Fee management & fee reports | — | **Phase 2** |
| Check in/out with temperature & health check | Only `can_pickup` flag | **Phase 3** |
| Enrolment & prospects (no e-form) | CSV import only | **Phase 4** |
| Student e-portfolio | — | **Phase 5** |
| Health activity tracker (meals, nap, toilet) | — | **Phase 6** (preschool only) |

## Conventions for every phase

- **Tenancy:** every new table carries `school_id`; every controller checks school access the
  same way `Operations/*` controllers do. Each phase adds cases to `TenantIsolationTest`.
- **Module toggles:** store enabled modules in `schools.settings.modules` (e.g. `{"fees": true}`),
  expose them on `auth/me`, and hide nav entries in `roleNavigation.js` when off. Aided schools
  can then run without fees; preschools can turn on the care tracker.
- **Migrations:** one migration per phase, continuing the `0001_01_01_0000NN` numbering (next is `000021`).
- **Money:** store amounts as integer paise (`unsignedBigInteger`), never floats.
- **Notifications:** go through the existing `WhatsAppService` / `SmsService` drivers, queued jobs,
  and `notification_logs` with a new `type` value.
- **i18n:** every UI string in `en.json` and `ml.json`; server messages in `lang/{en,ml}/edubridge.php`.
- **Tests:** a feature test file per phase (like `SchoolOperationsTest`) covering the happy path,
  role access and tenant isolation.

---

## Phase 0 — Ship what is on `dev` (S)

1. Open PR `dev` → `main`; let CI run `php artisan test` and the build.
2. Merge → self-hosted runner deploys. Confirm migration `000020` ran, queue worker and cron are live.
3. Switch production to `EDUBRIDGE_SMS_DRIVER=http` and the real WhatsApp bridge.
4. Pilot onboarding per `docs/PILOT.md` (real contacts, CSV import, parent invites).
5. Update `docs/PILOT.md`: CSV import is no longer "future".

## Phase 1 — Admin dashboard & management reports (S–M) — built

No new tables — aggregates data we already have. Also gives us the pilot adoption metrics.
Not cached yet (aggregate queries only); add caching if it gets slow on large schools.
"Parents who logged in" is not tracked (no last-login column), so adoption uses notice reads.

**Backend**
- `App\Services\Admin\AdminDashboardService`.
- `GET api/admin/dashboard` (school_admin, super_admin).
- Widgets:
  - Today's attendance: % present per class, absentees, absence alerts sent.
  - Notices: published in last 7 days, read rate (`notice_reads` / audience size).
  - Feedback: open threads, oldest unresolved age.
  - Checklists: today's / this week's completion.
  - Centre log: incidents in last 7 days.
  - Parent adoption: parents linked, parents who logged in, parents who read a notice (PILOT target 70%).
  - Delivery health: failed WhatsApp/SMS in `notification_logs` last 24 h.
- CSV export on existing reports (attendance, checklists) via `?format=csv`.

**Frontend**
- Rebuild `AdminHomePage.vue` as a dashboard of stat tiles linking to each module.

**Done when:** admin opens the app and sees today's school at a glance; numbers match the module pages.

## Phase 2 — Fee management (L) — built (v1)

Highest commercial value for unaided / CBSE / preschool customers.

**Decided:** invoice and receipt numbers are configurable per school (format tokens
`{SEQ:n}`, `{AY}`, `{YYYY}`, `{YY}`, `{MM}`, `{CODE}`; counter restarts per academic year,
per calendar year or never; admin can set the next number). No late fees in v1. Online
payment is Phase 2b; v1 records cash / UPI / bank / cheque at the office.
Reminder timing is set in `config/edubridge.php` (`fees.*`), not per school yet.

**Tables (migration `000021`)**
- `fee_heads` — school_id, name (Tuition, Bus, PTA, Books…), is_active.
- `fee_structures` — school_id, academic_year_id, school_class_id (nullable = all classes),
  fee_head_id, label (e.g. "Term 1"), amount_paise, due_on.
- `fee_concessions` — student_id, fee_head_id (nullable = all), type (percent/amount), value,
  reason, approved_by.
- `fee_invoices` — school_id, student_id, academic_year_id, number (unique per school),
  issued_on, due_on, total_paise, discount_paise, paid_paise, status
  (draft/issued/partially_paid/paid/void), void_reason.
- `fee_invoice_lines` — fee_invoice_id, fee_head_id, description, amount_paise.
- `fee_payments` — school_id, fee_invoice_id, amount_paise, method (cash/upi/bank/cheque/online),
  reference, receipt_number (unique per school), received_by, paid_at.

**Flows**
- Admin sets up heads and structures per class for the academic year.
- "Generate invoices" for a class / term → one invoice per active student, concessions applied.
- Record payment (full or part) → receipt number, parent gets WhatsApp receipt.
- Void invoice / payment with reason (never delete money records).
- `fees:send-reminders` scheduled command → WhatsApp to parents N days before/after due date.

**Reports**
- Collections by date range and method (day-book).
- Outstanding by class; defaulters list with days overdue.
- Student ledger (all invoices + payments).
- CSV export for all of the above.

**Parent portal**
- Fees tab on `MyChildPage`: invoices, balance, receipts (printable HTML / PDF).

**Phase 2b (later):** online payment via Razorpay/UPI — payment link on invoice, webhook
records `fee_payments` with `method=online`, idempotent on gateway payment id.


## Phase 3 — Check in / check out (M)

**Table (migration `000022`)**
- `student_check_events` — school_id, student_id, type (in/out), occurred_at, recorded_by,
  student_contact_id (nullable — who dropped/collected), person_name (when not a listed contact),
  temperature_c decimal(4,1) nullable, health_flags json (fever, cough, rash, injury, other),
  note, override_reason (nullable).

**Rules**
- Check-in creates/updates today's `attendance_records` row as `present` (or `late` after a
  configurable time), so attendance and check-in never disagree.
- Check-out must pick a contact with `can_pickup = true`; anyone else needs an admin override
  with a reason, which is logged.
- Temperature above a configurable threshold (default 37.5 °C) or any health flag → flagged,
  and a `centre_logs` entry (category `health`) is created automatically.
- Parent notified on check-in and check-out (WhatsApp, respects opt-in).

**Frontend**
- `CheckInPage.vue` — tablet "gate mode": pick class → tap student → in/out sheet with contact
  picker, temperature and health checkboxes. Large touch targets, works on a shared device.
- Today's board: who is in, who has left, who has not arrived.

**Settings:** temperature required yes/no, threshold, late-after time.

## Phase 4 — Enrolment & prospects (M)

Excludes online application e-forms, like LittleLives.

**Tables (migration `000023`)**
- `enquiries` — school_id, academic_year_id, applying_for_class_id, child_name, date_of_birth,
  parent_name, phone, email, source (walk_in/phone/website/referral/other), status
  (new/visit_scheduled/visited/applied/offered/admitted/declined/lost), assigned_to,
  next_follow_up_on, lost_reason, student_id (set on conversion).
- `enquiry_activities` — enquiry_id, user_id, type (note/call/visit/status_change), body, occurred_at.

**Flows**
- Office staff add enquiries; optional public enquiry form at `/enquire/{school code}`
  (throttled like `schools/register`).
- Pipeline board by status; follow-up list for today / overdue.
- "Admit" converts an enquiry into a `Student` + parent `User` + `parent_student` link,
  reusing the logic in `ParentStudentImportService`.
- Reports: funnel by stage, conversion by source, enquiries per class.

## Phase 5 — Student e-portfolio (M–L)

**Tables (migration `000024`)**
- `portfolio_entries` — school_id, author_id, school_class_id, type
  (observation/work_sample/milestone), title, body, domain (language, numeracy, motor,
  social-emotional, creative, other), observed_on, visible_to_parents, published_at.
- `portfolio_entry_student` — pivot, so one group photo/observation can tag several students.
- `portfolio_media` — portfolio_entry_id, disk, path, mime, size; stored with the
  `edubridge.attachments` disk and served via temporary URLs, like notice attachments.

**Flows**
- Teacher posts from phone: photos (compressed client-side), note, tag students.
- Parents see only entries that tag their child and are `visible_to_parents`.
- Timeline per student; filter by domain and term.
- Later: term-end portfolio export (PDF).

**Privacy:** a parent must never see untagged children's entries; covered by tests.

## Phase 6 — Daily care tracker (preschool / KG only) (S–M)

Behind the `care` module toggle.

**Table (migration `000025`)**
- `care_logs` — school_id, student_id, recorded_by, type (meal/nap/toilet/diaper/fluid/medication),
  occurred_at, data json (e.g. meal: all/most/some/none; nap: start/end), note.

**Flows**
- Quick-entry grid for a class: tap students, tap activity, save.
- Parent sees today's timeline on `MyChildPage`; optional end-of-day WhatsApp summary.

---

## Suggested order and sizing

| Phase | Size | Depends on |
|---|---|---|
| 0. Ship `dev` | S | — |
| 1. Admin dashboard | S–M | 0 |
| 2. Fees | L | 1 (dashboard widget), decisions above |
| 3. Check in/out | M | Attendance, student contacts |
| 4. Enrolment | M | CSV import service |
| 5. e-Portfolio | M–L | Attachment storage |
| 6. Care tracker | S–M | Module toggles |

If the next customers are aided schools (little or no fees), swap Phases 2 and 3.
