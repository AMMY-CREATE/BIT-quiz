# BIT Quiz - Intranet Quiz Platform

Production-ready intranet quiz platform for college/lab environments. Supports 100+ concurrent students, live admin monitoring, and Excel export.

## Features

- **Student Authentication**: Roll No, Name, Class, Quiz Code
- **Admin Authentication**: Secure DB-backed login (username/password)
- **Quiz System**: MCQ, timer-based, one question per screen
- **Anti-Cheat**: Tab switch detection, refresh logging, auto-submit after 5 violations
- **Live Admin Dashboard**: Real-time student list (Name, Roll No, Q#, Time Left, Status)
- **Data Export**: 3 CSV files (Student_Details, Student_Responses, Final_Results) – opens in Excel
- **No Results to Students**: Students never see marks/pass/fail

## Requirements

- XAMPP (Apache + MySQL + PHP 7.4+)
- Intranet/LAN (no external internet required)

## Setup

### 1. Install XAMPP

Install XAMPP and start **Apache** and **MySQL**.

### 2. Create Database

1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Import `database.sql` (File → Import → Choose database.sql)

Or via CLI:
```
c:\xampp\mysql\bin\mysql.exe -u root -p < "c:\xampp\htdocs\BIT quiz\database.sql"
```

### 3. Configure Database

Edit `config.php` if your MySQL credentials differ:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'mysql');  // Your MySQL password
define('DB_NAME', 'bit_quiz');
```

### 4. Default Admin Login

- **Username**: admin  
- **Password**: admin123  

**Change this in production.** Update password via phpMyAdmin:
```sql
UPDATE admin_users SET password_hash = '$2y$10$...' WHERE username = 'admin';
```
Generate hash: `c:\xampp\php\php.exe -r "echo password_hash('your_new_password', PASSWORD_DEFAULT);"`

## Usage

### Student Flow

1. Go to `http://localhost/BIT quiz/` (or your intranet URL)
2. Enter Roll No, Name, Class, Quiz Code (provided by instructor)
3. Click **Start Quiz**
4. Answer one question per screen. Use Next/Previous
5. Submit when done or when time runs out

### Admin Flow

1. Go to `http://localhost/BIT quiz/admin_login.php`
2. Login with admin credentials
3. **Create Quiz**: Set title, quiz code, time limit, marks
4. **Add Questions**: Add new questions to bank, then add them to quizzes
5. **Live Observation**: See students in real time (refreshes every 2 seconds)
6. **Export**: Download 3 CSV files (Student_Details, Student_Responses, Final_Results)

## Folder Structure

```
BIT quiz/
├── index.php              # Student login
├── admin_login.php        # Admin login
├── admin.php              # Admin dashboard
├── auth_student.php       # Student authentication
├── quiz.php               # Quiz interface
├── submit_quiz.php        # Submit quiz (no results shown)
├── api_heartbeat.php      # Live status updates
├── api_save_progress.php  # Auto-save answers
├── log_tab_switch.php     # Tab switch logging
├── get_observing_data.php # Admin live API
├── export_excel.php       # Export 3 CSV files
├── config.php             # DB config
├── database.sql           # Schema
├── style.css
├── .htaccess
└── README.md
```

## Security

- Prepared statements for all DB queries (SQL injection prevention)
- Session-based auth for admin and students
- Input validation on all forms
- Activity logging for audit trail
- Export files generated on-demand (not stored on disk)

## Concurrency

- Indexes on student_attempts, active_sessions, tab_switches
- Batch inserts for student_responses
- Single UPDATE for heartbeat (low DB load per student)

## License

For internal use.
