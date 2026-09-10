-- =====================================================
-- PAYROLL & BENEFITS MANAGEMENT SYSTEM
-- Database Schema for MySQL (XAMPP)
-- Client: TRI-M GLOBAL LOGISTICS & TRADING INC.
-- =====================================================

CREATE DATABASE IF NOT EXISTS payroll_benefits_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE payroll_benefits_db;

-- =====================================================
-- USERS (HR Login)
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  email       VARCHAR(255) NOT NULL UNIQUE,
  password    VARCHAR(255) NOT NULL,
  name        VARCHAR(255) NOT NULL,
  role        ENUM('Admin','HR','Employee') NOT NULL DEFAULT 'HR',
  initials    VARCHAR(5)   NOT NULL DEFAULT '',
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- DEPARTMENTS
-- =====================================================
CREATE TABLE IF NOT EXISTS departments (
  id   INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- =====================================================
-- EMPLOYEES
-- =====================================================
CREATE TABLE IF NOT EXISTS employees (
  id                VARCHAR(20)  PRIMARY KEY,
  code              VARCHAR(30)  NOT NULL UNIQUE,
  first_name        VARCHAR(100) NOT NULL,
  middle_name       VARCHAR(100) DEFAULT NULL,
  last_name         VARCHAR(100) NOT NULL,
  suffix            VARCHAR(20)  DEFAULT NULL,
  email             VARCHAR(255) DEFAULT NULL,
  mobile            VARCHAR(20)  DEFAULT NULL,
  birth_date        DATE         DEFAULT NULL,
  gender            ENUM('Male','Female','Other') DEFAULT NULL,
  department        VARCHAR(100) NOT NULL,
  position          VARCHAR(100) NOT NULL,
  employment_type   ENUM('Regular','Probationary','Contractual') NOT NULL DEFAULT 'Regular',
  hire_date         DATE         NOT NULL,
  basic_salary      DECIMAL(12,2) NOT NULL DEFAULT 0,
  status            ENUM('Active','On Leave','Resigned','Terminated') NOT NULL DEFAULT 'Active',
  -- Government IDs
  sss               VARCHAR(30)  DEFAULT NULL,
  philhealth        VARCHAR(30)  DEFAULT NULL,
  pagibig           VARCHAR(30)  DEFAULT NULL,
  tin               VARCHAR(30)  DEFAULT NULL,
  -- E-Wallet (integrated)
  ewallet_provider  VARCHAR(30)  DEFAULT NULL,
  ewallet_account   VARCHAR(50)  DEFAULT NULL,
  ewallet_name      VARCHAR(100) DEFAULT NULL,
  ewallet_primary   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- SALARY HISTORY
-- =====================================================
CREATE TABLE IF NOT EXISTS salary_history (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  employee_id    VARCHAR(20)   NOT NULL,
  basic_salary   DECIMAL(12,2) NOT NULL,
  effective_date DATE          NOT NULL,
  reason         VARCHAR(255)  DEFAULT NULL,
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- ALLOWANCES
-- =====================================================
CREATE TABLE IF NOT EXISTS allowances (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  employee_id  VARCHAR(20)   NOT NULL,
  type         VARCHAR(50)   NOT NULL,
  amount       DECIMAL(12,2) NOT NULL DEFAULT 0,
  frequency    ENUM('Monthly','Quarterly','Annual','One-Time') NOT NULL DEFAULT 'Monthly',
  is_active    TINYINT(1)    NOT NULL DEFAULT 1,
  effective_date DATE        DEFAULT NULL,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- LOANS / DEDUCTIONS
-- =====================================================
CREATE TABLE IF NOT EXISTS loans (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  employee_id       VARCHAR(20)   NOT NULL,
  type              VARCHAR(50)   NOT NULL,
  total_amount      DECIMAL(12,2) NOT NULL DEFAULT 0,
  monthly_deduction DECIMAL(12,2) NOT NULL DEFAULT 0,
  remaining_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
  start_date        DATE          DEFAULT NULL,
  created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- ATTENDANCE LOGS
-- =====================================================
CREATE TABLE IF NOT EXISTS attendance_logs (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  employee_id  VARCHAR(20)  NOT NULL,
  log_date     DATE         NOT NULL,
  status       ENUM('P','H','A','OT') NOT NULL DEFAULT 'P',
  ot_hours     DECIMAL(4,1) NOT NULL DEFAULT 0,
  notes        TEXT         DEFAULT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_emp_date (employee_id, log_date),
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- PAYROLL RUNS (Header)
-- =====================================================
CREATE TABLE IF NOT EXISTS payroll_runs (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  period       VARCHAR(50)   NOT NULL,
  period_start DATE          NOT NULL,
  period_end   DATE          NOT NULL,
  total_gross  DECIMAL(14,2) NOT NULL DEFAULT 0,
  total_deductions DECIMAL(14,2) NOT NULL DEFAULT 0,
  total_net    DECIMAL(14,2) NOT NULL DEFAULT 0,
  status       ENUM('Draft','Processing','Approved','Paid') NOT NULL DEFAULT 'Draft',
  run_date     DATETIME      DEFAULT NULL,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- PAYROLL ITEMS (Line items per employee per run)
-- =====================================================
CREATE TABLE IF NOT EXISTS payroll_items (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  payroll_run_id  INT           NOT NULL,
  employee_id     VARCHAR(20)   NOT NULL,
  employee_name   VARCHAR(200)  NOT NULL,
  department      VARCHAR(100)  DEFAULT NULL,
  days_worked     DECIMAL(4,1)  NOT NULL DEFAULT 22,
  ot_hours        DECIMAL(6,1)  NOT NULL DEFAULT 0,
  basic_pay       DECIMAL(12,2) NOT NULL DEFAULT 0,
  overtime_pay    DECIMAL(12,2) NOT NULL DEFAULT 0,
  allowances      DECIMAL(12,2) NOT NULL DEFAULT 0,
  claims_amount   DECIMAL(12,2) NOT NULL DEFAULT 0,
  gross_pay       DECIMAL(12,2) NOT NULL DEFAULT 0,
  sss_ee          DECIMAL(10,2) NOT NULL DEFAULT 0,
  sss_er          DECIMAL(10,2) NOT NULL DEFAULT 0,
  philhealth_ee   DECIMAL(10,2) NOT NULL DEFAULT 0,
  philhealth_er   DECIMAL(10,2) NOT NULL DEFAULT 0,
  pagibig_ee      DECIMAL(10,2) NOT NULL DEFAULT 0,
  pagibig_er      DECIMAL(10,2) NOT NULL DEFAULT 0,
  withholding_tax DECIMAL(10,2) NOT NULL DEFAULT 0,
  loans_deduction DECIMAL(10,2) NOT NULL DEFAULT 0,
  total_deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
  net_pay         DECIMAL(12,2) NOT NULL DEFAULT 0,
  ewallet_provider VARCHAR(30)  DEFAULT NULL,
  status          ENUM('Draft','Approved','Paid') NOT NULL DEFAULT 'Draft',
  is_included     TINYINT(1)    NOT NULL DEFAULT 1,
  pay_date        DATE          DEFAULT NULL,
  FOREIGN KEY (payroll_run_id) REFERENCES payroll_runs(id) ON DELETE CASCADE,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- CLAIM CATEGORIES
-- =====================================================
CREATE TABLE IF NOT EXISTS claim_categories (
  id         VARCHAR(30) PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  max_amount DECIMAL(12,2) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- =====================================================
-- CLAIMS
-- =====================================================
CREATE TABLE IF NOT EXISTS claims (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  claim_number   VARCHAR(30)   NOT NULL UNIQUE,
  employee_name  VARCHAR(200)  NOT NULL,
  employee_id    VARCHAR(20)   DEFAULT NULL,
  category       VARCHAR(100)  NOT NULL,
  description    TEXT          DEFAULT NULL,
  amount         DECIMAL(12,2) NOT NULL DEFAULT 0,
  claim_date     DATE          NOT NULL,
  status         ENUM('Pending','AI Review','Approved','Rejected','Paid') NOT NULL DEFAULT 'Pending',
  ai_confidence  INT           NOT NULL DEFAULT 0,
  recipient_confidence TINYINT UNSIGNED NOT NULL DEFAULT 0,
  recipient_method VARCHAR(100) NOT NULL DEFAULT 'Manual',
  recipient_decision ENUM('Accepted','Overridden','Rejected','Pending') NOT NULL DEFAULT 'Pending',
  ai_fraud_score INT           NOT NULL DEFAULT 0,
  receipt_file   VARCHAR(255)  DEFAULT NULL,
  receipt_hash   CHAR(64)      DEFAULT NULL,
  receipt_mime   VARCHAR(100)  DEFAULT NULL,
  receipt_verification_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  receipt_verification_status VARCHAR(40) NOT NULL DEFAULT 'Not verified',
  receipt_verification_notes TEXT DEFAULT NULL,
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL,
  INDEX idx_claim_employee_date (employee_id, claim_date),
  INDEX idx_claim_receipt_hash (receipt_hash)
) ENGINE=InnoDB;

-- =====================================================
-- AI RECIPIENT PREDICTIONS / HR FEEDBACK
-- =====================================================
CREATE TABLE IF NOT EXISTS ai_predictions (
  id                      INT AUTO_INCREMENT PRIMARY KEY,
  claim_id                INT DEFAULT NULL,
  description_normalized  TEXT NOT NULL,
  suggested_employee_id   VARCHAR(20) DEFAULT NULL,
  selected_employee_id    VARCHAR(20) DEFAULT NULL,
  recipient_confidence    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  match_method            VARCHAR(100) NOT NULL DEFAULT 'No match',
  recipient_decision      ENUM('Accepted','Overridden','Rejected','Pending') NOT NULL DEFAULT 'Pending',
  candidate_snapshot      JSON DEFAULT NULL,
  created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_prediction_selected (selected_employee_id),
  INDEX idx_prediction_suggested (suggested_employee_id),
  FOREIGN KEY (claim_id) REFERENCES claims(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- RECEIPT VERIFICATION RESULTS
-- =====================================================
CREATE TABLE IF NOT EXISTS claim_receipt_verifications (
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  claim_id              INT NOT NULL,
  image_readable        TINYINT(1) NOT NULL DEFAULT 0,
  ocr_available         TINYINT(1) NOT NULL DEFAULT 0,
  extracted_merchant    VARCHAR(255) DEFAULT NULL,
  extracted_receipt_date DATE DEFAULT NULL,
  extracted_amount      DECIMAL(12,2) DEFAULT NULL,
  extracted_or_number   VARCHAR(100) DEFAULT NULL,
  extracted_tin         VARCHAR(100) DEFAULT NULL,
  extracted_text        TEXT DEFAULT NULL,
  amount_matches        TINYINT(1) NOT NULL DEFAULT 0,
  date_within_period    TINYINT(1) NOT NULL DEFAULT 0,
  duplicate_claim_id    INT DEFAULT NULL,
  verification_score    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  issues                JSON DEFAULT NULL,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_receipt_verification_claim (claim_id),
  FOREIGN KEY (claim_id) REFERENCES claims(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- BENEFIT PLANS (Catalog)
-- =====================================================
CREATE TABLE IF NOT EXISTS benefit_plans (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  plan_code       VARCHAR(30)   NOT NULL UNIQUE,
  plan_name       VARCHAR(200)  NOT NULL,
  plan_type       VARCHAR(50)   NOT NULL,
  provider        VARCHAR(100)  NOT NULL,
  monthly_premium DECIMAL(12,2) NOT NULL DEFAULT 0,
  employer_share  INT           NOT NULL DEFAULT 0,
  employee_share  INT           NOT NULL DEFAULT 0,
  description     TEXT          DEFAULT NULL,
  is_active       TINYINT(1)    NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- =====================================================
-- BENEFIT ENROLLMENTS
-- =====================================================
CREATE TABLE IF NOT EXISTS benefit_enrollments (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  employee_id     VARCHAR(20)   NOT NULL,
  employee_name   VARCHAR(200)  NOT NULL,
  plan_id         INT           NOT NULL,
  plan_name       VARCHAR(200)  NOT NULL,
  provider        VARCHAR(100)  NOT NULL,
  monthly_premium DECIMAL(12,2) NOT NULL DEFAULT 0,
  employer_share  INT           NOT NULL DEFAULT 0,
  employee_share  INT           NOT NULL DEFAULT 0,
  dependents      INT           NOT NULL DEFAULT 0,
  effective_date  DATE          NOT NULL,
  status          ENUM('Active','Pending','Cancelled') NOT NULL DEFAULT 'Active',
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  FOREIGN KEY (plan_id)     REFERENCES benefit_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- LEAVE BALANCES
-- =====================================================
CREATE TABLE IF NOT EXISTS leave_balances (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  employee_id VARCHAR(20)  DEFAULT NULL,
  leave_type  VARCHAR(50)  NOT NULL,
  accrued     INT          NOT NULL DEFAULT 0,
  used        INT          NOT NULL DEFAULT 0,
  balance     INT          NOT NULL DEFAULT 0,
  year        INT          NOT NULL DEFAULT 2026
) ENGINE=InnoDB;

-- =====================================================
-- LEAVE CONVERSIONS
-- =====================================================
CREATE TABLE IF NOT EXISTS leave_conversions (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  employee_id VARCHAR(20)  DEFAULT NULL,
  leave_type  VARCHAR(50)  NOT NULL,
  days        INT          NOT NULL DEFAULT 0,
  daily_rate  DECIMAL(12,2) NOT NULL DEFAULT 0,
  amount      DECIMAL(12,2) NOT NULL DEFAULT 0,
  reason      VARCHAR(255) DEFAULT NULL,
  conv_date   DATE         NOT NULL,
  status      ENUM('Pending','Approved','Rejected','Paid') NOT NULL DEFAULT 'Pending',
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- 13TH MONTH PAY REPORT
-- =====================================================
CREATE TABLE IF NOT EXISTS thirteenth_month (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  employee_id     VARCHAR(20)   NOT NULL,
  employee_name   VARCHAR(200)  NOT NULL,
  department      VARCHAR(100)  DEFAULT NULL,
  monthly_basic   DECIMAL(12,2) NOT NULL DEFAULT 0,
  months_worked   INT           NOT NULL DEFAULT 12,
  computed_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  year            INT           NOT NULL DEFAULT 2026,
  status          ENUM('Pending','Approved','Paid') NOT NULL DEFAULT 'Pending',
  payment_date    DATE          DEFAULT NULL,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- NOTIFICATIONS
-- =====================================================
CREATE TABLE IF NOT EXISTS notifications (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  message    VARCHAR(500) NOT NULL,
  type       VARCHAR(50)  NOT NULL DEFAULT 'General',
  is_read    TINYINT(1)   NOT NULL DEFAULT 0,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- AUDIT LOG
-- =====================================================
CREATE TABLE IF NOT EXISTS audit_log (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT          DEFAULT NULL,
  user_name  VARCHAR(200) DEFAULT NULL,
  action     VARCHAR(100) NOT NULL,
  details    TEXT         DEFAULT NULL,
  ip_address VARCHAR(45)  DEFAULT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- E-WALLET PROVIDERS (Reference)
-- =====================================================
CREATE TABLE IF NOT EXISTS ewallet_providers (
  id    INT AUTO_INCREMENT PRIMARY KEY,
  value VARCHAR(30)  NOT NULL UNIQUE,
  label VARCHAR(30)  NOT NULL,
  type  ENUM('mobile','bank','cash','other') NOT NULL DEFAULT 'other'
) ENGINE=InnoDB;

-- #####################################################
-- SEED DATA
-- #####################################################

-- Users
INSERT INTO users (email, password, name, role, initials) VALUES
('admin@company.com', '$2y$10$YourHashedPasswordHere1234567890abc', 'Admin User', 'Admin', 'AU'),
('hr@company.com',    '$2y$10$YourHashedPasswordHere1234567890abc', 'HR User',    'HR',    'HU');

-- Departments
INSERT INTO departments (name) VALUES
('IT Department'), ('HR Department'), ('Finance Department'), ('Marketing'), ('Operations');

-- Employees
INSERT INTO employees (id, code, first_name, middle_name, last_name, suffix, email, mobile, birth_date, gender, department, position, employment_type, hire_date, basic_salary, status, sss, philhealth, pagibig, tin, ewallet_provider, ewallet_account, ewallet_name, ewallet_primary) VALUES
('emp-001', 'EMP-2026-001', 'Juan',     'Carlos',  'Dela Cruz',   NULL,  'juan.delacruz@company.com',     '09171234567', '1990-05-15', 'Male',   'IT Department',      'Senior Software Engineer',  'Regular', '2025-01-15', 65000.00, 'Active',    '12-3456789-0', '12-345678901-2', '1234-5678-9012', '123-456-789', 'GCash',        '09171234567',  'Juan Dela Cruz',      1),
('emp-002', 'EMP-2026-002', 'Maria',    'Isabel',  'Santos',      NULL,  'maria.santos@company.com',      '09281234567', '1988-11-22', 'Female', 'HR Department',      'HR Manager',                'Regular', '2024-03-01', 72000.00, 'Active',    '09-8765432-1', '09-876543210-1', '9876-5432-1098', '987-654-321', 'Maya',         '09281234567',  'Maria Santos',        1),
('emp-003', 'EMP-2026-003', 'Jose',     'Miguel',  'Reyes',       NULL,  'jose.reyes@company.com',        '09391234567', '1992-03-08', 'Male',   'Finance Department', 'Accountant',                'Regular', '2025-06-15', 58000.00, 'Active',    '56-7890123-4', '56-789012345-6', '5678-9012-3456', '567-890-123', 'GCash',        '09391234567',  'Jose Reyes',          1),
('emp-004', 'EMP-2026-004', 'Anna',     'Marie',   'Garcia',      NULL,  'anna.garcia@company.com',       '09451234567', '1995-07-30', 'Female', 'Marketing',          'Marketing Specialist',      'Regular', '2025-09-01', 52000.00, 'On Leave',  '23-4567890-1', '23-456789012-3', '2345-6789-0123', '234-567-890', 'Bank',         '1234567890123','Anna Garcia',         1),
('emp-005', 'EMP-2026-005', 'Miguel',   'Antonio', 'Fernandez',   NULL,  'miguel.fernandez@company.com',  '09561234567', '1987-01-12', 'Male',   'Operations',         'Operations Manager',        'Regular', '2024-11-01', 78000.00, 'Active',    '34-5678901-2', '34-567890123-4', '3456-7890-1234', '345-678-901', 'Bank',         '2345678901234','Miguel Fernandez',    1),
('emp-006', 'EMP-2026-006', 'Liza',     'Ann',     'Tan',         NULL,  'liza.tan@company.com',          '09561234568', '1993-09-25', 'Female', 'IT Department',      'QA Engineer',               'Regular', '2024-08-15', 48000.00, 'Resigned',  '45-6789012-3', '45-678901234-5', '4567-8901-2345', '456-789-012', 'GCash',        '09561234567',  'Liza Tan',            1),
('emp-007', 'EMP-2026-007', 'Ramon',    'Luis',    'Villanueva',  NULL,  'ramon.villanueva@company.com',  '09671234567', '1991-12-03', 'Male',   'Operations',         'Operations Supervisor',     'Regular', '2025-04-01', 62000.00, 'Active',    '67-8901234-5', '67-890123456-7', '6789-0123-4567', '678-901-234', 'Maya',         '09671234567',  'Ramon Villanueva',    1),
('emp-008', 'EMP-2026-008', 'Cristina', 'Grace',   'Lopez',       NULL,  'cristina.lopez@company.com',    '09781234567', '1996-04-18', 'Female', 'HR Department',      'HR Associate',              'Regular', '2025-07-15', 45000.00, 'Active',    '78-9012345-6', '78-901234567-8', '7890-1234-5678', '789-012-345', 'Cash',         '',             'Cristina Lopez',      1);

-- Salary History
INSERT INTO salary_history (employee_id, basic_salary, effective_date, reason) VALUES
('emp-001', 60000.00, '2025-01-15', 'Initial salary on hire'),
('emp-001', 65000.00, '2026-01-15', 'Annual merit increase (8%)'),
('emp-002', 65000.00, '2024-03-01', 'Initial salary on hire'),
('emp-002', 72000.00, '2026-01-01', 'Promoted to HR Manager'),
('emp-003', 55000.00, '2025-06-15', 'Initial salary on hire'),
('emp-003', 58000.00, '2026-01-01', 'Performance-based increase'),
('emp-004', 50000.00, '2025-09-01', 'Initial salary on hire'),
('emp-004', 52000.00, '2026-01-01', 'Merit increase'),
('emp-005', 72000.00, '2024-11-01', 'Initial salary on hire'),
('emp-005', 78000.00, '2026-01-01', 'Promoted to Operations Manager'),
('emp-006', 45000.00, '2024-08-15', 'Initial salary on hire'),
('emp-006', 48000.00, '2025-08-15', 'Annual increase'),
('emp-007', 58000.00, '2025-04-01', 'Initial salary on hire'),
('emp-007', 62000.00, '2026-01-01', 'Merit increase'),
('emp-008', 43000.00, '2025-07-15', 'Initial salary on hire'),
('emp-008', 45000.00, '2026-01-01', 'Probationary completion increase');

-- Allowances
INSERT INTO allowances (employee_id, type, amount, frequency, is_active) VALUES
('emp-001', 'Rice',          2000.00, 'Monthly', 1),
('emp-001', 'Transport',     1500.00, 'Monthly', 1),
('emp-001', 'Communication', 1000.00, 'Monthly', 1),
('emp-001', 'Meal',          1500.00, 'Monthly', 1),
('emp-001', 'Clothing',      5000.00, 'Annual',  1),
('emp-002', 'Rice',          2000.00, 'Monthly', 1),
('emp-002', 'Transport',     1500.00, 'Monthly', 1),
('emp-002', 'Communication', 1000.00, 'Monthly', 1),
('emp-002', 'Meal',          1500.00, 'Monthly', 1),
('emp-002', 'Housing',       5000.00, 'Monthly', 1),
('emp-003', 'Rice',          2000.00, 'Monthly', 1),
('emp-003', 'Transport',     1000.00, 'Monthly', 1),
('emp-003', 'Communication',  500.00, 'Monthly', 0),
('emp-004', 'Rice',          2000.00, 'Monthly', 1),
('emp-004', 'Transport',     1000.00, 'Monthly', 1),
('emp-004', 'Meal',          1000.00, 'Monthly', 1),
('emp-005', 'Rice',          2000.00, 'Monthly', 1),
('emp-005', 'Transport',     2000.00, 'Monthly', 1),
('emp-005', 'Communication', 1500.00, 'Monthly', 1),
('emp-005', 'Meal',          1500.00, 'Monthly', 1),
('emp-005', 'Housing',       5000.00, 'Monthly', 1),
('emp-006', 'Rice',          2000.00, 'Monthly', 1),
('emp-006', 'Transport',     1000.00, 'Monthly', 1),
('emp-007', 'Rice',          2000.00, 'Monthly', 1),
('emp-007', 'Transport',     1500.00, 'Monthly', 1),
('emp-007', 'Communication',  500.00, 'Monthly', 1),
('emp-007', 'Meal',          1000.00, 'Monthly', 1),
('emp-008', 'Rice',          2000.00, 'Monthly', 1),
('emp-008', 'Transport',     1000.00, 'Monthly', 1);

-- Loans
INSERT INTO loans (employee_id, type, total_amount, monthly_deduction, remaining_balance) VALUES
('emp-001', 'SSS Loan',      20000.00,   1000.00,  12000.00),
('emp-002', 'Car Loan',     500000.00,   8000.00, 450000.00),
('emp-003', 'Salary Advance', 10000.00,  2000.00,   8000.00),
('emp-005', 'Housing Loan', 2000000.00, 15000.00, 1850000.00),
('emp-007', 'Pag-IBIG Loan',  50000.00,  1500.00,  35000.00);

-- Attendance Logs (Aug 18-29, 2026)
INSERT INTO attendance_logs (employee_id, log_date, status, ot_hours, notes) VALUES
-- Aug 18
('emp-001', '2026-08-18', 'P',  0, ''),
('emp-002', '2026-08-18', 'P',  0, ''),
('emp-003', '2026-08-18', 'P',  0, ''),
('emp-005', '2026-08-18', 'OT', 4, 'System deployment'),
('emp-007', '2026-08-18', 'P',  0, ''),
('emp-008', '2026-08-18', 'P',  0, ''),
-- Aug 19
('emp-001', '2026-08-19', 'P',  0, ''),
('emp-002', '2026-08-19', 'P',  0, ''),
('emp-003', '2026-08-19', 'P',  0, ''),
('emp-005', '2026-08-19', 'P',  0, ''),
('emp-007', '2026-08-19', 'P',  0, ''),
('emp-008', '2026-08-19', 'A',  0, 'Emergency leave'),
-- Aug 20
('emp-001', '2026-08-20', 'OT', 3, 'Feature release prep'),
('emp-002', '2026-08-20', 'P',  0, ''),
('emp-003', '2026-08-20', 'P',  0, ''),
('emp-005', '2026-08-20', 'P',  0, ''),
('emp-007', '2026-08-20', 'H',  0, 'Doctor appointment'),
('emp-008', '2026-08-20', 'P',  0, ''),
-- Aug 21
('emp-001', '2026-08-21', 'P',  0, ''),
('emp-002', '2026-08-21', 'OT', 2, 'Payroll closing'),
('emp-003', '2026-08-21', 'P',  0, ''),
('emp-005', '2026-08-21', 'P',  0, ''),
('emp-007', '2026-08-21', 'P',  0, ''),
('emp-008', '2026-08-21', 'P',  0, ''),
-- Aug 22
('emp-001', '2026-08-22', 'H',  0, 'Personal errand'),
('emp-002', '2026-08-22', 'P',  0, ''),
('emp-003', '2026-08-22', 'A',  0, 'Sick leave'),
('emp-005', '2026-08-22', 'P',  0, ''),
('emp-007', '2026-08-22', 'P',  0, ''),
('emp-008', '2026-08-22', 'P',  0, ''),
-- Aug 25
('emp-001', '2026-08-25', 'P',  0, ''),
('emp-002', '2026-08-25', 'P',  0, ''),
('emp-003', '2026-08-25', 'P',  0, ''),
('emp-005', '2026-08-25', 'P',  0, ''),
('emp-007', '2026-08-25', 'P',  0, ''),
('emp-008', '2026-08-25', 'P',  0, ''),
-- Aug 26
('emp-001', '2026-08-26', 'P',  0, ''),
('emp-002', '2026-08-26', 'P',  0, ''),
('emp-003', '2026-08-26', 'P',  0, ''),
('emp-005', '2026-08-26', 'OT', 2, 'Quarter-end ops review'),
('emp-007', '2026-08-26', 'P',  0, ''),
('emp-008', '2026-08-26', 'P',  0, ''),
-- Aug 27
('emp-001', '2026-08-27', 'P',  0, ''),
('emp-002', '2026-08-27', 'P',  0, ''),
('emp-003', '2026-08-27', 'P',  0, ''),
('emp-005', '2026-08-27', 'P',  0, ''),
('emp-007', '2026-08-27', 'P',  0, ''),
('emp-008', '2026-08-27', 'P',  0, ''),
-- Aug 28
('emp-001', '2026-08-28', 'P',  0, ''),
('emp-002', '2026-08-28', 'P',  0, ''),
('emp-003', '2026-08-28', 'P',  0, ''),
('emp-005', '2026-08-28', 'P',  0, ''),
('emp-007', '2026-08-28', 'OT', 2, 'Inventory reconciliation'),
('emp-008', '2026-08-28', 'P',  0, ''),
-- Aug 29
('emp-001', '2026-08-29', 'P',  0, ''),
('emp-002', '2026-08-29', 'P',  0, ''),
('emp-003', '2026-08-29', 'P',  0, ''),
('emp-005', '2026-08-29', 'P',  0, ''),
('emp-007', '2026-08-29', 'P',  0, ''),
('emp-008', '2026-08-29', 'P',  0, '');

-- Payroll Runs
INSERT INTO payroll_runs (id, period, period_start, period_end, total_gross, total_deductions, total_net, status, run_date) VALUES
(1, 'January 2026',  '2026-01-01', '2026-01-31', 215000.00, 17200.00, 178800.00, 'Paid',     '2026-01-31 09:00:00'),
(2, 'February 2026', '2026-02-01', '2026-02-28', 195000.00, 15600.00, 163700.00, 'Draft',    NULL);

-- Payroll Items
INSERT INTO payroll_items (payroll_run_id, employee_id, employee_name, department, days_worked, ot_hours, basic_pay, overtime_pay, allowances, claims_amount, gross_pay, sss_ee, sss_er, philhealth_ee, philhealth_er, pagibig_ee, pagibig_er, withholding_tax, loans_deduction, total_deductions, net_pay, ewallet_provider, status, is_included, pay_date) VALUES
(1, 'emp-001', 'Juan Dela Cruz',    'IT Department', 22, 0, 65000, 0, 6000, 0, 71000, 900, 1900, 1300, 1300, 100, 100, 5250, 1000, 8550, 62450, 'GCash', 'Paid', 1, '2026-01-31'),
(1, 'emp-002', 'Maria Santos',      'HR Department', 22, 0, 72000, 0, 11000, 0, 83000, 900, 1900, 1440, 1440, 100, 100, 6250, 8000, 16690, 66310, 'Maya', 'Paid', 1, '2026-01-31'),
(1, 'emp-005', 'Miguel Fernandez',  'Operations',    22, 0, 78000, 0, 12000, 0, 90000, 900, 1900, 1560, 1560, 100, 100, 7500, 15000, 25060, 64940, 'Bank', 'Paid', 1, '2026-01-31'),
(2, 'emp-001', 'Juan Dela Cruz',    'IT Department', 22, 0, 65000, 0, 6000, 0, 71000, 900, 1900, 1300, 1300, 100, 100, 5250, 1000, 8550, 62450, 'GCash', 'Draft', 1, '2026-02-28'),
(2, 'emp-002', 'Maria Santos',      'HR Department', 22, 0, 72000, 0, 11000, 0, 83000, 900, 1900, 1440, 1440, 100, 100, 6250, 8000, 16690, 66310, 'Maya', 'Draft', 1, '2026-02-28'),
(2, 'emp-003', 'Jose Reyes',        'Finance Department', 22, 0, 58000, 0, 3000, 0, 61000, 900, 1900, 1160, 1160, 100, 100, 4200, 2000, 8360, 52640, 'GCash', 'Draft', 1, '2026-02-28');

-- Claim Categories
INSERT INTO claim_categories (id, name, max_amount) VALUES
('cat-transport', 'Transportation',  1000.00),
('cat-meal',      'Meal Allowance',   500.00),
('cat-medical',   'Medical',         5000.00),
('cat-supplies',  'Office Supplies', 2000.00),
('cat-training',  'Training',       10000.00),
('cat-ot',        'Overtime',         300.00);

-- Claims
INSERT INTO claims (claim_number, employee_name, category, description, amount, claim_date, status, ai_confidence, ai_fraud_score) VALUES
('CLM202601150001', 'John Dela Cruz', 'Transportation', 'Grab ride from Makati to BGC for client meeting',      350.00, '2026-01-15', 'Pending',   92, 15),
('CLM202601200002', 'Maria Santos',   'Medical',        'Consultation fee at St. Luke\'s Hospital',             1200.00, '2026-01-20', 'Approved',  88, 10),
('CLM202601250003', 'Carlos Reyes',   'Meal Allowance', 'Lunch with client at Conrad Hotel',                    500.00, '2026-01-25', 'Rejected',  75, 45),
('CLM202602010004', 'Ana Martinez',   'Office Supplies', 'Printer ink and paper for department',                 850.00, '2026-02-01', 'Paid',      95,  5),
('CLM202602050005', 'Ramon Garcia',   'Transportation', 'Taxi ride to airport for business trip',                650.00, '2026-02-05', 'AI Review', 85, 25);

-- Benefit Plans
INSERT INTO benefit_plans (plan_code, plan_name, plan_type, provider, monthly_premium, employer_share, employee_share, description, is_active) VALUES
('HMO-GOLD',   'Health Maintenance Organization - Gold',   'HMO',            'Maxicare',  2500.00, 60, 40, 'Comprehensive health coverage with 100K annual limit', 1),
('HMO-SILVER', 'Health Maintenance Organization - Silver', 'HMO',            'Medicard',  1800.00, 50, 50, 'Basic health coverage with 50K annual limit',          1),
('LIFE-500',   'Life Insurance - 500K',                    'Life Insurance',  'Sun Life',  1200.00, 50, 50, '500K life insurance coverage',                         1),
('RET-01',     'Retirement Plan',                          'Retirement',      'BDO Trust', 1500.00, 100, 0, 'Retirement savings plan with full employer contribution', 1);

-- Benefit Enrollments
INSERT INTO benefit_enrollments (employee_id, employee_name, plan_id, plan_name, provider, monthly_premium, employer_share, employee_share, dependents, effective_date, status) VALUES
('emp-001', 'Juan Dela Cruz',   1, 'HMO Gold',           'Maxicare',  2500.00, 60, 40, 2, '2026-01-01', 'Active'),
('emp-001', 'Juan Dela Cruz',   3, 'Life Insurance 500K','Sun Life',  1200.00, 50, 50, 1, '2026-01-01', 'Active'),
('emp-002', 'Maria Santos',     1, 'HMO Gold',           'Maxicare',  2500.00, 60, 40, 1, '2026-01-01', 'Active'),
('emp-005', 'Miguel Fernandez', 4, 'Retirement Plan',    'BDO Trust', 1500.00, 100, 0, 0, '2026-02-01', 'Pending');

-- Leave Balances
INSERT INTO leave_balances (employee_id, leave_type, accrued, used, balance, year) VALUES
(NULL, 'Vacation',  15, 2, 13, 2026),
(NULL, 'Sick',      10, 1,  9, 2026),
(NULL, 'Emergency',  5, 0,  5, 2026),
(NULL, 'Special',    3, 0,  3, 2026);

-- Leave Conversions
INSERT INTO leave_conversions (leave_type, days, daily_rate, amount, conv_date, status, reason) VALUES
('Vacation',  5, 2045.45, 10227.25, '2026-01-30', 'Approved', 'Personal expenses'),
('Sick',      3, 2045.45,  6136.35, '2026-02-10', 'Pending',  'Medical bills'),
('Vacation',  2, 2045.45,  4090.90, '2026-03-05', 'Paid',     'Family vacation'),
('Emergency', 3, 2045.45,  6136.35, '2026-03-20', 'Rejected', 'Insufficient balance');

-- 13th Month
INSERT INTO thirteenth_month (employee_id, employee_name, department, monthly_basic, months_worked, computed_amount, year, status) VALUES
('emp-001', 'Juan Dela Cruz',    'IT Department',      65000.00, 12, 65000.00, 2026, 'Approved'),
('emp-002', 'Maria Santos',      'HR Department',      72000.00,  8, 48000.00, 2026, 'Approved'),
('emp-003', 'Jose Reyes',        'Finance Department', 58000.00, 10, 48333.33, 2026, 'Approved'),
('emp-004', 'Anna Garcia',       'Marketing',          52000.00, 12, 52000.00, 2026, 'Pending'),
('emp-005', 'Miguel Fernandez',  'Operations',         78000.00,  6, 39000.00, 2026, 'Paid'),
('emp-006', 'Liza Tan',          'IT Department',      48000.00, 11, 44000.00, 2026, 'Approved'),
('emp-007', 'Ramon Villanueva',  'Operations',         62000.00, 12, 62000.00, 2026, 'Approved'),
('emp-008', 'Cristina Lopez',    'HR Department',      45000.00,  9, 33750.00, 2026, 'Pending');

-- Notifications
INSERT INTO notifications (message, type, is_read, created_at) VALUES
('Payroll for February 2026 processed',  'Payroll',      0, '2026-02-15 09:00:00'),
('New employee added: Cristina Lopez',   'Employee',     0, '2026-02-14 14:30:00'),
('Salary updated for Juan Dela Cruz',    'Compensation', 1, '2026-02-13 10:00:00'),
('Claim CLM202601200002 approved',       'Claims',       1, '2026-01-21 11:00:00'),
('Benefits enrollment updated',         'Benefits',     1, '2026-01-05 08:30:00');

-- Audit Log
INSERT INTO audit_log (user_name, action, details, created_at) VALUES
('HR User',    'Payroll Processed', 'February 2026 payroll run initiated',           '2026-02-15 09:00:00'),
('HR User',    'Employee Added',    'Added new employee: Cristina Lopez (EMP-2026-008)', '2026-02-14 14:30:00'),
('Admin User', 'Salary Updated',   'Juan Dela Cruz salary updated to ₱65,000',       '2026-02-13 10:00:00'),
('HR User',    'Claim Approved',   'Claim CLM202601200002 approved for Maria Santos', '2026-01-21 11:00:00'),
('HR User',    'Benefits Updated', 'Juan Dela Cruz enrolled in HMO Gold plan',        '2026-01-05 08:30:00');

-- E-Wallet Providers
INSERT INTO ewallet_providers (value, label, type) VALUES
('GCash',        'GCash',        'mobile'),
('Maya',         'Maya',         'mobile'),
('PayMaya',      'PayMaya',      'mobile'),
('Bank',         'Bank',         'bank'),
('Company Bank', 'Company Bank', 'bank'),
('Cash',         'Cash',         'cash'),
('Other',        'Other',        'other');
