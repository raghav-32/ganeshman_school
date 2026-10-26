# Mobile API v1 — Students & Teachers

JSON API for the Android app. Admin features are **not** exposed here (admin accounts can't sign in).

Base URL: `{BASE_URL}api/v1/` — e.g. `https://your-domain/api/v1/student/dashboard.php`

## Setup (once)
Run the **v5 section at the bottom of `database.sql`** (creates `api_tokens`, `exams.results_published`, `book_requests`).
`api_tokens` is also auto-created on first login if you forget.

## Conventions
- Send/receive JSON. `Content-Type: application/json` on POST bodies.
- Auth: `Authorization: Bearer <token>` (fallback header if your host strips it: `X-Auth-Token: <token>`).
- Every response:
  ```json
  { "success": true,  "message": "OK", "data": { ... } }
  { "success": false, "message": "Human readable reason", "errors": { "field": "why" } }
  ```
- Status codes: `200` ok · `201` created · `400` bad JSON · `401` not signed in / token expired (→ show login) · `403` wrong role / not your class · `404` not found · `405` wrong method · `409` conflict · `422` validation · `500` server error.
- Dates are AD `YYYY-MM-DD`; every date also comes with a `*_bs` string (Bikram Sambat, e.g. `"09 Kartik 2083"`). Times are `HH:MM`.
- Tokens last 30 days and extend while the app is used. Store the token in Android Keystore / EncryptedSharedPreferences.
- Text is returned as plain text (the web forms store HTML-escaped text; the API decodes it).

## Auth
| Method | Endpoint | Body | Notes |
|---|---|---|---|
| POST | `auth/login.php` | `{username, password, device_name?}` | `username` = username **or** email. Returns `token`, `expires_at`, `role`, `profile` |
| POST | `auth/logout.php` | – | Revokes this device's token |
| GET | `auth/me.php` | – | Current profile (student or teacher) |
| POST | `auth/change_password.php` | `{current_password, new_password}` | Min 6 chars. Signs out other devices |

## Student (`role = student`)
| Method | Endpoint | Query / body |
|---|---|---|
| GET | `student/dashboard.php` | attendance %, latest published results, fee totals, issued books, 4 notices |
| GET | `student/profile.php` | |
| GET | `student/attendance.php` | `?month=2026-10` → overall + month summary + daily records |
| GET | `student/results.php` | list of published exams; `?exam_id=` → subject-wise marks, grade, pass/fail |
| GET | `student/exams.php` | published exam schedules (date, time, venue per subject) |
| GET | `student/fees.php` | totals, per-fee status (Paid/Partial/Unpaid), payment history |
| GET | `student/timetable.php` | Sunday–Friday, periods with teacher/room |
| GET | `student/notices.php` | `?limit=50&offset=0` |
| GET | `student/library.php` | `?q=&limit=&offset=` books + my issued (with overdue fine, Rs 5/day) + my requests |
| POST | `student/library_request.php` | `{book_id}` → 201; 409 if unavailable / already requested |
| POST | `student/library_cancel.php` | `{request_id}` |

## Teacher (`role = teacher`)
| Method | Endpoint | Query / body |
|---|---|---|
| GET | `teacher/dashboard.php` | profile, my classes, classes marked today, 4 notices |
| GET | `teacher/classes.php` | classes I'm linked to (`is_class_teacher`, `student_count`) |
| GET | `teacher/students.php` | `?class_id=` students + attendance rate |
| GET | `teacher/attendance.php` | `?class_id=&date=` students with status (`null` = not marked), `can_mark` |
| POST | `teacher/attendance.php` | `{class_id, date, records:[{student_id, status}]}` — status `Present/Absent/Late/Leave` (`P`/`A` ok) |
| GET | `teacher/exams.php` | exams of my classes (`can_enter_marks`) |
| GET | `teacher/marks.php` | `?exam_id=&subject_id=` subjects, students, existing marks |
| POST | `teacher/marks.php` | `{exam_id, subject_id, marks:[{student_id, marks_obtained}]}` |
| GET | `teacher/notices.php` | `?limit=&offset=` |

Rules (same as the web panel, now enforced on the server): only the **class teacher** can mark attendance / enter marks for a class; teachers only see classes they're linked to (class teacher, timetable or assignment). Extra checks: no future attendance dates, students must belong to the class, marks must be within `0..full_marks`. Saves are all-or-nothing and re-saving overwrites.

## Examples
```bash
# login
curl -X POST https://HOST/api/v1/auth/login.php -H 'Content-Type: application/json' \
     -d '{"username":"s_hari","password":"secret","device_name":"Pixel 8"}'

# student dashboard
curl https://HOST/api/v1/student/dashboard.php -H 'Authorization: Bearer <token>'

# mark attendance
curl -X POST https://HOST/api/v1/teacher/attendance.php -H 'Authorization: Bearer <token>' \
     -H 'Content-Type: application/json' \
     -d '{"class_id":12,"date":"2026-10-26","records":[{"student_id":1,"status":"Present"},{"student_id":2,"status":"Absent"}]}'
```

## Hosting notes
- Android 9+ blocks plain `http://` by default. `BASE_URL` in `includes/config.php` is currently `http://…` — use HTTPS for the app.
- Free hosts such as InfinityFree (`*.42web.io`) put a JavaScript bot-check in front of requests; a native app can't pass it and will receive an HTML page instead of JSON. If login returns HTML, move the site to a host without that check.
- Existing `api/attendance.php` is the old session-cookie endpoint used by the web panel; the app should use `api/v1/` only.
