# Parent & student CSV import

School admins can bulk-add classes, students, and parents using a CSV file.

## Download sample

- In the app: **School Admin → Import parents** → **Download sample CSV**
- Direct link: `/samples/edubridge-parent-student-import.csv`

## CSV columns

| Column | Required | Example | Notes |
|--------|----------|---------|-------|
| `student_name` | Yes | `Anu` | Child's full name |
| `admission_number` | No | `STMS-2025-042` | Unique per school; auto-generated if empty |
| `class` | Yes | `3` | Class name (LP/UP/HS number or label) |
| `section` | Yes | `B` | Section within class |
| `parent_name` | Yes | `Anu's Mother` | Guardian name for the app |
| `parent_phone` | Yes | `9123456789` | 10-digit Indian mobile; used for OTP login |
| `relationship` | No | `mother` | `mother`, `father`, `guardian`, `grandparent`, or `other` (default: `guardian`) |

## What happens on upload

1. Uses the school's **current academic year** (or creates `2025-26` if missing).
2. Creates **class** and **section** automatically if they do not exist.
3. Creates or updates **student** records.
4. Creates **parent** users and assigns the `parent` role at the school.
5. Links parent ↔ student in `parent_student`.

Parents can then log in with their phone number and OTP.

## Tips

- Save as **UTF-8 CSV** (Excel: Save As → CSV UTF-8).
- One row = one student + one primary parent.
- Duplicate `admission_number` in the same school updates the existing student.
- Same `parent_phone` with multiple rows links one parent to multiple children.
