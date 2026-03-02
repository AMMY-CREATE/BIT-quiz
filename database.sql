-- ========================================================
-- BIT Quiz - Full Database Schema (Production)
-- Import via phpMyAdmin or: mysql -u root -p < database.sql
-- ========================================================

CREATE DATABASE IF NOT EXISTS bit_quiz CHARACTER SET utf8 COLLATE utf8_general_ci;
USE bit_quiz;

-- --------------------------------------------------------
-- Table: admin_users
-- Secure admin authentication (session-based)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Default admin: username=admin, password=admin123 (CHANGE IN PRODUCTION!)
INSERT IGNORE INTO admin_users (username, password_hash) VALUES
('admin', '$2y$10$WVT2c5teWNyAkyRlkpEuAO2NdrTUIQSjSr31AhXyc/.NnCXkeJG/i');

-- --------------------------------------------------------
-- Table: quizzes
-- Quiz metadata: time limit, total marks, question weightage
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS quizzes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(200) NOT NULL,
    quiz_code       VARCHAR(20) NOT NULL UNIQUE COMMENT 'Code students enter to join',
    time_limit_sec  INT NOT NULL DEFAULT 1800,
    total_marks     INT NOT NULL DEFAULT 30,
    marks_per_q     INT NOT NULL DEFAULT 1,
    is_active       TINYINT(1) DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT NULL,
    INDEX idx_quiz_code (quiz_code),
    INDEX idx_quiz_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
-- Table: questions (standalone question bank)
-- Can be linked to multiple quizzes via quiz_questions
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS questions (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    question_text  TEXT NOT NULL,
    option_a       VARCHAR(500) NOT NULL,
    option_b       VARCHAR(500) NOT NULL,
    option_c       VARCHAR(500) NOT NULL,
    option_d       VARCHAR(500) NOT NULL,
    correct_option CHAR(1) NOT NULL COMMENT 'A, B, C or D',
    marks          INT NOT NULL DEFAULT 1,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_questions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
-- Table: quiz_questions
-- Links questions to quizzes with optional ordering
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS quiz_questions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id     INT NOT NULL,
    question_id INT NOT NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    INDEX idx_quiz_questions (quiz_id),
    INDEX idx_question_quiz (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
-- Table: student_attempts
-- One row per student per quiz attempt (login/submit times)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS student_attempts (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    student_id     VARCHAR(50) NOT NULL,
    student_name   VARCHAR(150) NOT NULL,
    student_class  VARCHAR(100) NOT NULL,
    quiz_id        INT NOT NULL,
    quiz_code      VARCHAR(20) NOT NULL,
    login_time     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submit_time    TIMESTAMP NULL,
    status         ENUM('ACTIVE','SUBMITTED','DISCONNECTED','TIMEOUT','AUTO_SUBMIT') DEFAULT 'ACTIVE',
    total_questions INT NOT NULL DEFAULT 0,
    correct_count  INT NOT NULL DEFAULT 0,
    wrong_count    INT NOT NULL DEFAULT 0,
    final_marks    DECIMAL(10,2) NOT NULL DEFAULT 0,
    time_taken_sec INT NOT NULL DEFAULT 0,
    violation_count INT NOT NULL DEFAULT 0,
    session_id     VARCHAR(200) NULL,
    last_heartbeat TIMESTAMP NULL,
    current_q_num  INT NULL COMMENT 'Last known question number',
    time_remaining INT NULL COMMENT 'Seconds left at last heartbeat',
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    UNIQUE KEY uq_student_quiz (student_id, quiz_id),
    INDEX idx_attempt_quiz (quiz_id),
    INDEX idx_attempt_status (status),
    INDEX idx_attempt_heartbeat (last_heartbeat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
-- Table: student_responses
-- Individual answers (optimized for batch inserts)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS student_responses (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id     INT NOT NULL,
    student_id     VARCHAR(50) NOT NULL,
    quiz_id        INT NOT NULL,
    question_id    INT NOT NULL,
    question_text  TEXT NOT NULL,
    option_selected CHAR(1) NULL,
    correct_option CHAR(1) NOT NULL,
    is_correct     TINYINT(1) NOT NULL DEFAULT 0,
    answered_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attempt_id) REFERENCES student_attempts(id) ON DELETE CASCADE,
    INDEX idx_responses_attempt (attempt_id),
    INDEX idx_responses_student_quiz (student_id, quiz_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
-- Table: active_sessions
-- Tracks current active quiz sessions (for multi-device / live view)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS active_sessions (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    student_id    VARCHAR(50) NOT NULL,
    quiz_id       INT NOT NULL,
    attempt_id    INT NOT NULL,
    session_id    VARCHAR(200) NOT NULL,
    last_seen     DATETIME DEFAULT NULL,
    UNIQUE KEY uq_student_quiz (student_id, quiz_id),
    INDEX idx_active_quiz (quiz_id),
    INDEX idx_active_lastseen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
-- Table: tab_switches
-- Logs tab switch events (for violation tracking)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS tab_switches (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    student_id  VARCHAR(50) NOT NULL,
    quiz_id     INT NOT NULL,
    switched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tab_student_quiz (student_id, quiz_id),
    INDEX idx_tab_time (switched_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
-- Table: suspicious_logs
-- General suspicious activity (refresh, multi-device, etc.)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS suspicious_logs (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    student_id    VARCHAR(50) NOT NULL,
    quiz_id       INT NOT NULL,
    event_type    VARCHAR(100) NOT NULL,
    event_details TEXT,
    logged_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_susp_student_quiz (student_id, quiz_id),
    INDEX idx_susp_time (logged_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
-- Table: quiz_progress
-- Auto-save answers on disconnect (last answered question + all answers)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS quiz_progress (
    attempt_id    INT PRIMARY KEY,
    answers_json  TEXT COMMENT 'JSON: {0:"A",1:"B",...}',
    current_q     INT DEFAULT 0,
    updated_at    DATETIME DEFAULT NULL,
    FOREIGN KEY (attempt_id) REFERENCES student_attempts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
-- Table: activity_log
-- Admin/student activity for audit trail
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    actor_type  ENUM('ADMIN','STUDENT') NOT NULL,
    actor_id    VARCHAR(100) NOT NULL,
    action      VARCHAR(100) NOT NULL,
    details     TEXT,
    ip_address  VARCHAR(45),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_log_actor (actor_type, actor_id),
    INDEX idx_log_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
-- Sample questions (30 questions)
-- --------------------------------------------------------
INSERT INTO questions (question_text, option_a, option_b, option_c, option_d, correct_option) VALUES
('Which data structure uses the LIFO principle?', 'Queue', 'Linked List', 'Stack', 'Array', 'C'),
('Who is known as the father of the World Wide Web?', 'Bill Gates', 'Tim Berners-Lee', 'Steve Jobs', 'Mark Zuckerberg', 'B'),
('What does HTTP stand for?', 'HyperText Transfer Protocol', 'HyperText Technical Process', 'High Transfer Text Protocol', 'Hyperlink Total Transfer Protocol', 'A'),
('Which language is used for Android development?', 'Swift', 'Kotlin', 'Objective-C', 'C#', 'B'),
('What does RAM stand for?', 'Read Access Memory', 'Random Access Memory', 'Ready Active Module', 'Rapid Access Memory', 'B'),
('Which is NOT an operating system?', 'Linux', 'Windows', 'Oracle', 'macOS', 'C'),
('What is the chemical symbol for Gold?', 'Ag', 'Fe', 'Au', 'Gd', 'C'),
('Which planet is the Red Planet?', 'Venus', 'Mars', 'Jupiter', 'Saturn', 'B'),
('Who painted the Mona Lisa?', 'Vincent van Gogh', 'Pablo Picasso', 'Leonardo da Vinci', 'Claude Monet', 'C'),
('What is the capital of Japan?', 'Seoul', 'Beijing', 'Tokyo', 'Bangkok', 'C'),
('Which organ filters blood?', 'Heart', 'Lungs', 'Kidneys', 'Liver', 'C'),
('What is the largest ocean?', 'Atlantic', 'Indian', 'Arctic', 'Pacific', 'D'),
('Who wrote Romeo and Juliet?', 'Charles Dickens', 'William Shakespeare', 'Mark Twain', 'Jane Austen', 'B'),
('What is the square root of 144?', '10', '11', '12', '13', 'C'),
('Which gas do plants absorb for photosynthesis?', 'Oxygen', 'Nitrogen', 'Carbon Dioxide', 'Hydrogen', 'C'),
('Currency of the United Kingdom?', 'Euro', 'Dollar', 'Pound Sterling', 'Yen', 'C'),
('Smallest prime number?', '0', '1', '2', '3', 'C'),
('Main component of natural gas?', 'Ethane', 'Propane', 'Butane', 'Methane', 'D'),
('In which year did the Titanic sink?', '1905', '1912', '1918', '1923', 'B'),
('Hardest natural substance on Earth?', 'Gold', 'Iron', 'Diamond', 'Graphite', 'C'),
('Which company developed Java?', 'Microsoft', 'Sun Microsystems', 'Apple', 'IBM', 'B'),
('What does SQL stand for?', 'Simple Query Language', 'Structured Query Language', 'System Quality Log', 'Standard Query Logic', 'B'),
('Which layer of Earth is liquid?', 'Crust', 'Mantle', 'Outer Core', 'Inner Core', 'C'),
('First person to walk on the moon?', 'Yuri Gagarin', 'Buzz Aldrin', 'Neil Armstrong', 'John Glenn', 'C'),
('Most spoken language in the world?', 'English', 'Spanish', 'Mandarin Chinese', 'Hindi', 'C'),
('Land of the Rising Sun?', 'China', 'South Korea', 'Japan', 'Thailand', 'C'),
('Boiling point of water at sea level?', '90°C', '100°C', '110°C', '120°C', 'B'),
('Element with atomic number 1?', 'Helium', 'Oxygen', 'Hydrogen', 'Carbon', 'C'),
('Who discovered gravity?', 'Albert Einstein', 'Isaac Newton', 'Galileo Galilei', 'Nikola Tesla', 'B'),
('Largest mammal in the world?', 'Elephant', 'Blue Whale', 'Giraffe', 'Shark', 'B');

-- --------------------------------------------------------
-- Sample quiz (optional - admin can create more)
-- --------------------------------------------------------
INSERT INTO quizzes (title, quiz_code, time_limit_sec, total_marks, marks_per_q, is_active) VALUES
('General Knowledge Quiz', 'GK2025', 1800, 30, 1, 1);

-- Link first 30 questions to sample quiz
INSERT INTO quiz_questions (quiz_id, question_id, sort_order)
SELECT 1, id, id FROM questions ORDER BY id LIMIT 30;
