# AttendanceTrack — KCA University
## Setup Instructions

### Step 1: Copy Files to XAMPP
Copy the entire `AttendanceTrack` folder into:
```
C:\xampp\htdocs\attendancetrack\
```
So you should have:
- `C:\xampp\htdocs\attendancetrack\index.html`
- `C:\xampp\htdocs\attendancetrack\api.php`

### Step 2: Run the SQL Migration
1. Open phpMyAdmin → `http://localhost/phpmyadmin`
2. Select your database: `controlled_student_attendance_system`
3. Click **SQL** tab
4. Paste contents of `setup.sql` and click **Go**

This adds the missing columns (full_name, admission_number, email, phone) to your `students` table.

### Step 3: Open the App
In your browser go to:
```
http://localhost/attendancetrack/index.html
```

### Login Credentials (from your database)
Use the existing credentials from your `users` table.

### Features
- ✅ Dark/Light mode toggle
- ✅ Live clock in navbar
- ✅ Real calendar dropdown
- ✅ Student: Fingerprint scan, attendance stats, records
- ✅ Lecturer: Courses, open/close sessions, attendance reports
- ✅ Admin: System overview, add users (with email/phone/admission no.), add courses
- ✅ Absent 3+ times → automatic email alert to student
- ✅ Attendance report shows total marks deducted per semester
- ✅ KCA University logo on login page
- ✅ No demo buttons — real working system against your XAMPP database

### Email Alerts
For email alerts to work, configure XAMPP's `php.ini`:
- Set `SMTP = smtp.gmail.com` (or your mail server)
- Set `smtp_port = 587`
Or use PHPMailer for full SMTP support.
