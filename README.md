# 🏫 Ganeshman Singh Academy – School Management System
**Location:** Jhiljhille, Jhapa, Nepal

---

## 📁 Complete File Structure (39 Files)

```
ganeshman_school/
│
├── index.php                        ← Login page (role switcher)
├── logout.php                       ← Session destroy & redirect
├── database.sql                     ← Full MySQL schema + sample data
│
├── includes/
│   ├── config.php                   ← DB config, Database class (Singleton)
│   ├── auth.php                     ← Auth, session, helpers, sanitize
│   ├── admin_header.php             ← Admin sidebar + topbar layout
│   ├── admin_footer.php             ← Admin closing tags + JS
│   ├── teacher_header.php           ← Teacher panel sidebar + topbar
│   ├── teacher_footer.php           ← Teacher closing tags + JS
│   ├── student_header.php           ← Student portal sidebar + topbar
│   └── student_footer.php           ← Student closing tags + JS
│
├── assets/
│   ├── css/style.css                ← Full responsive stylesheet (900+ lines)
│   └── js/main.js                   ← AJAX, modals, charts, attendance, toast
│
├── api/
│   └── attendance.php               ← REST API: submit/get/summary (AJAX)
│
├── admin/                           ← 🔐 ADMIN PANEL
│   ├── dashboard.php                ← Stats cards, bar chart, donut chart
│   ├── students.php                 ← Full CRUD, search, filter by class
│   ├── teachers.php                 ← Full CRUD, salary, qualifications
│   ├── classes.php                  ← Create/delete classes & sections
│   ├── subjects.php                 ← Subject management per class
│   ├── attendance.php               ← Monthly view, daily drill-down
│   ├── exams.php                    ← Create exams, publish/draft toggle
│   ├── marks.php                    ← Enter marks, results overview, rank
│   ├── fees.php                     ← Fee structure, payments, receipt print
│   ├── library.php                  ← Books, issue/return, auto-fine calc
│   ├── notices.php                  ← Post/delete announcements
│   ├── timetable.php                ← Visual builder, conflict detection
│   └── reports.php                  ← Analytics, charts, overdue books
│
├── teacher/                         ← 👨‍🏫 TEACHER PANEL
│   ├── dashboard.php                ← Welcome banner, class list, notices
│   ├── attendance.php               ← AJAX attendance marking grid
│   ├── marks.php                    ← Enter marks with live grade preview
│   ├── students.php                 ← View students + attendance rates
│   └── notices.php                  ← School notice board
│
└── student/                         ← 🎓 STUDENT PANEL
    ├── dashboard.php                ← Welcome banner, results, notices
    ├── profile.php                  ← Personal + guardian info
    ├── attendance.php               ← Monthly records + progress bar
    ├── results.php                  ← Exam results with grade bars
    ├── timetable.php                ← Weekly class schedule
    └── notices.php                  ← School announcements
```

---

## 🚀 Installation Guide

### Step 1 — Requirements
- XAMPP / WAMP / LAMP (PHP 8.0+, MySQL 5.7+)
- phpMyAdmin
- Browser (Chrome, Firefox)

### Step 2 — Place Files
Copy the `ganeshman_school/` folder to:
- **XAMPP:** `C:/xampp/htdocs/ganeshman_school/`
- **WAMP:** `C:/wamp64/www/ganeshman_school/`
- **Linux:** `/var/www/html/ganeshman_school/`

### Step 3 — Create Database
1. Open **phpMyAdmin** → `http://localhost/phpmyadmin`
2. Click **"New"** → Database Name: `ganeshman_school` → Create
3. Click **"Import"** → Choose `database.sql` → Go

### Step 4 — Configure Database
Edit `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');      // Your MySQL username
define('DB_PASS', '');          // Your MySQL password
define('DB_NAME', 'ganeshman_school');
```

### Step 5 — Open the System
```
http://localhost/ganeshman_school/
```

---

## 🔐 Default Login Credentials

| Role    | Username | Password   |
|---------|----------|------------|
| Admin   | admin    | admin123   |
| Teacher | (created by admin) | teacher123 |
| Student | (created by admin) | student123 |

> **Note:** Passwords are bcrypt-hashed. Change defaults after first login.

---

## ✅ Features Checklist

### 🔐 Authentication
- [x] Secure role-based login (Admin / Teacher / Student)
- [x] PHP session handling with cookie security
- [x] bcrypt password hashing
- [x] Logout with session destroy
- [x] Role-based redirect after login

### 🧑‍💼 Admin Panel
- [x] Dashboard with stats, bar chart, donut chart
- [x] Student CRUD (add/edit/delete + auto ID generation)
- [x] Teacher CRUD (add/edit/delete + auto ID generation)
- [x] Class & section management
- [x] Subject management per class
- [x] Attendance monitoring (monthly + daily drill-down)
- [x] Exam management (create, publish/draft toggle)
- [x] Marks entry with rank table
- [x] Printable result sheet
- [x] Fee structure setup
- [x] Fee payment recording
- [x] Printable fee receipt
- [x] Library book catalog
- [x] Book issue & return
- [x] Automatic fine calculation (Rs 5/day)
- [x] Timetable builder with conflict detection
- [x] Notice board (post/delete, target audience)
- [x] Reports page: charts, top students, overdue books

### 👨‍🏫 Teacher Panel
- [x] Dashboard with assigned classes
- [x] **AJAX attendance marking** (no page reload)
- [x] Mark all Present / Absent buttons
- [x] Live attendance counter (Present/Absent/Late)
- [x] Load existing attendance for a date
- [x] Marks entry with live grade preview
- [x] View my students + attendance rates
- [x] Notice board

### 🎓 Student Panel
- [x] Personal dashboard with welcome banner
- [x] Profile with guardian info
- [x] Monthly attendance records
- [x] Attendance percentage progress bar
- [x] Published exam results with grade bars
- [x] Subject-wise result with rank
- [x] Printable result sheet
- [x] Weekly timetable view
- [x] Notice board

### 🔒 Security
- [x] SQL injection prevention (prepared statements throughout)
- [x] Input sanitization (htmlspecialchars + strip_tags)
- [x] Session-based auth with role enforcement
- [x] Role-based access control (requireRole())
- [x] HttpOnly session cookies

### 🎨 Frontend
- [x] Modern responsive dashboard UI
- [x] Google Fonts (Nunito + Playfair Display)
- [x] CSS variables for consistent theming
- [x] Card components, stat cards, badges, modals
- [x] Toast notifications system
- [x] Canvas bar charts & donut charts (no external library)
- [x] Mobile sidebar toggle
- [x] Print stylesheet

---

## 🗄️ Database Tables

| Table            | Purpose                          |
|------------------|----------------------------------|
| users            | Login accounts for all roles     |
| students         | Student profiles & guardian info |
| teachers         | Teacher profiles & salary        |
| classes          | Class & section definitions      |
| subjects         | Subjects per class               |
| teacher_assignments | Teacher–class–subject links   |
| attendance       | Date-wise attendance records     |
| exams            | Exam definitions                 |
| marks            | Student marks per subject/exam   |
| fee_structure    | Fee types per class              |
| fee_payments     | Payment records + receipts       |
| timetable        | Weekly class schedule            |
| notices          | Announcements                    |
| books            | Library catalog                  |
| issued_books     | Book issue/return + fines        |

---

## ⚙️ Adding Teachers & Students

1. Login as **Admin**
2. Go to **Teachers** → Add Teacher (set username + password)
3. Go to **Classes** → Create classes (e.g. "Class 10 A")
4. Go to **Subjects** → Add subjects for each class
5. Go to **Students** → Add Student → Assign to class
6. Teacher logs in → Goes to **Mark Attendance** → Selects class & date

---

## 📞 Support Notes

- **AJAX Attendance API:** `api/attendance.php` handles submit/get/summary (web panel only)
- **Mobile API (Android app):** `api/v1/` — student & teacher token API, see `api/v1/README.md`
- **Grade Scale:** A+(90+), A(80+), B+(70+), B(60+), C+(50+), C(40+), D(35+), F(<35)
- **Library Fine:** Rs 5 per day overdue
- **Timetable Conflict:** System auto-checks teacher double-booking
- **Receipt Numbers:** Auto-generated unique codes (RCP + uniqid)

**Android app:** native student/teacher app in [`android/`](android/README.md) (uses `api/v1`).
