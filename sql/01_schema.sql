-- ============================================================
--  NIGHTINGALE — Clinic Monitoring System
--  01 · BASE SCHEMA  (15 tables, 3NF, InnoDB)
--
--  Mirrors the schema defined in the Nightingale System Reference
--  Document v2.0, Section 06.  Run BEFORE 02_clinic_settings.sql
--  and 03_storedprocs_triggers.sql.
-- ============================================================

DROP DATABASE IF EXISTS nightingale_cms;
CREATE DATABASE nightingale_cms
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE nightingale_cms;

SET FOREIGN_KEY_CHECKS = 0;

-- ───────────────────────────── 1. department
CREATE TABLE department (
  dept_id   TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  dept_name VARCHAR(80)      NOT NULL,
  PRIMARY KEY (dept_id),
  UNIQUE KEY uq_dept_name (dept_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Company departments — lookup table';

-- ───────────────────────────── 2. employee
CREATE TABLE employee (
  employee_id       INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  employee_no       VARCHAR(20)     NOT NULL,
  first_name        VARCHAR(60)     NOT NULL,
  last_name         VARCHAR(60)     NOT NULL,
  birthdate         DATE            NOT NULL,
  gender            ENUM('M','F','Other') NOT NULL,
  blood_type        VARCHAR(5)      NULL,
  allergies         TEXT            NULL,
  emergency_contact VARCHAR(100)    NULL,
  emergency_phone   VARCHAR(20)     NULL,
  dept_id           TINYINT UNSIGNED NOT NULL,
  hire_date         DATE            NULL,
  PRIMARY KEY (employee_id),
  UNIQUE KEY uq_employee_no (employee_no),
  KEY idx_dept_id (dept_id),
  CONSTRAINT fk_employee_dept
    FOREIGN KEY (dept_id) REFERENCES department(dept_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Company employees — source of truth for patient data';

-- ───────────────────────────── 3. user_account (nurse + patient)
CREATE TABLE user_account (
  user_id      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  employee_id  INT UNSIGNED  NOT NULL,
  username     VARCHAR(60)   NOT NULL,
  email        VARCHAR(120)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role         ENUM('nurse','patient') NOT NULL DEFAULT 'patient',
  totp_secret  VARCHAR(64)   NULL,
  totp_enabled TINYINT(1)    NOT NULL DEFAULT 0,
  is_active    TINYINT(1)    NOT NULL DEFAULT 1,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login   DATETIME      NULL,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_employee_account (employee_id),
  UNIQUE KEY uq_username (username),
  UNIQUE KEY uq_email (email),
  CONSTRAINT fk_user_employee
    FOREIGN KEY (employee_id) REFERENCES employee(employee_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Login accounts for nurses and patients';

-- ───────────────────────────── 4. admin_account (isolated)
CREATE TABLE admin_account (
  admin_id      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  username      VARCHAR(60)   NOT NULL,
  email         VARCHAR(120)  NOT NULL,
  full_name     VARCHAR(120)  NOT NULL,
  password_hash VARCHAR(255)  NOT NULL,
  totp_secret   VARCHAR(64)   NULL,
  totp_enabled  TINYINT(1)    NOT NULL DEFAULT 0,
  is_active     TINYINT(1)    NOT NULL DEFAULT 1,
  last_login    DATETIME      NULL,
  PRIMARY KEY (admin_id),
  UNIQUE KEY uq_admin_username (username),
  UNIQUE KEY uq_admin_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Admin login — isolated from nurse/patient portal';

-- ───────────────────────────── 5. nurse_profile
CREATE TABLE nurse_profile (
  nurse_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id        INT UNSIGNED NOT NULL,
  license_no     VARCHAR(30)  NOT NULL,
  license_expiry DATE         NOT NULL,
  specialization VARCHAR(80)  NULL,
  PRIMARY KEY (nurse_id),
  UNIQUE KEY uq_nurse_user (user_id),
  UNIQUE KEY uq_license_no (license_no),
  CONSTRAINT fk_nurse_user
    FOREIGN KEY (user_id) REFERENCES user_account(user_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='PRC license and specialization for nurse accounts';

-- ───────────────────────────── 6. queue
CREATE TABLE queue (
  queue_id     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  employee_id  INT UNSIGNED  NOT NULL,
  queue_number SMALLINT UNSIGNED NOT NULL,
  queue_date   DATE          NOT NULL,
  time_in      TIME          NOT NULL,
  status       ENUM('waiting','in_progress','done','cancelled') NOT NULL DEFAULT 'waiting',
  PRIMARY KEY (queue_id),
  KEY idx_queue_date (queue_date),
  KEY idx_queue_employee (employee_id),
  CONSTRAINT fk_queue_employee
    FOREIGN KEY (employee_id) REFERENCES employee(employee_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Daily patient queue — one entry per visit attempt';

-- ───────────────────────────── 7. medicine
CREATE TABLE medicine (
  medicine_id     INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  medicine_name   VARCHAR(120)    NOT NULL,
  generic_name    VARCHAR(120)    NOT NULL,
  dosage_strength VARCHAR(40)     NOT NULL,
  unit            VARCHAR(20)     NOT NULL,
  category        VARCHAR(60)     NULL,
  min_stock       SMALLINT UNSIGNED NOT NULL DEFAULT 20,
  current_stock   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  expiry_date     DATE            NOT NULL,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (medicine_id),
  KEY idx_medicine_name (medicine_name),
  KEY idx_expiry (expiry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Clinic medicine master catalog and inventory';

-- ───────────────────────────── 8. consultation
CREATE TABLE consultation (
  consultation_id INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  queue_id        INT UNSIGNED  NOT NULL,
  employee_id     INT UNSIGNED  NOT NULL,
  nurse_id        INT UNSIGNED  NOT NULL,
  chief_complaint TEXT          NOT NULL,
  diagnosis       VARCHAR(200)  NULL,
  case_type       ENUM('illness','injury','follow_up','emergency') NOT NULL,
  nurse_notes     TEXT          NULL,
  work_status     ENUM('fit','light_duty','sick_leave','for_hospitalization') NOT NULL DEFAULT 'fit',
  consult_date    DATE          NOT NULL,
  time_start      TIME          NULL,
  time_end        TIME          NULL,
  PRIMARY KEY (consultation_id),
  UNIQUE KEY uq_consult_queue (queue_id),
  KEY idx_consult_employee (employee_id),
  KEY idx_consult_nurse (nurse_id),
  KEY idx_consult_date (consult_date),
  CONSTRAINT fk_consult_queue
    FOREIGN KEY (queue_id) REFERENCES queue(queue_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_consult_employee
    FOREIGN KEY (employee_id) REFERENCES employee(employee_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_consult_nurse
    FOREIGN KEY (nurse_id) REFERENCES nurse_profile(nurse_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Core clinical encounter record — one per patient visit';

-- ───────────────────────────── 9. vital_signs
CREATE TABLE vital_signs (
  vital_id        INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  consultation_id INT UNSIGNED    NOT NULL,
  bp_systolic     SMALLINT        NULL,
  bp_diastolic    SMALLINT        NULL,
  temperature     DECIMAL(4,1)    NULL,
  pulse_rate      SMALLINT        NULL,
  resp_rate       SMALLINT        NULL,
  o2_saturation   TINYINT UNSIGNED NULL,
  weight_kg       DECIMAL(5,2)    NULL,
  recorded_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (vital_id),
  UNIQUE KEY uq_vital_consult (consultation_id),
  CONSTRAINT fk_vital_consult
    FOREIGN KEY (consultation_id) REFERENCES consultation(consultation_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT chk_vitals CHECK (
    (bp_systolic IS NULL OR bp_systolic > 0)
    AND (bp_diastolic IS NULL OR bp_diastolic > 0)
    AND (temperature IS NULL OR (temperature BETWEEN 30 AND 45))
    AND (pulse_rate IS NULL OR pulse_rate > 0)
    AND (resp_rate IS NULL OR resp_rate > 0)
    AND (o2_saturation IS NULL OR o2_saturation BETWEEN 50 AND 100)
    AND (weight_kg IS NULL OR weight_kg > 0)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Vital signs per consultation — separated from consultation for 3NF';

-- ───────────────────────────── 10. referral
CREATE TABLE referral (
  referral_id     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  consultation_id INT UNSIGNED  NOT NULL,
  referral_type   ENUM('company_doctor','hospital','specialist','emergency') NOT NULL,
  referred_to     VARCHAR(150)  NOT NULL,
  reason          TEXT          NOT NULL,
  referral_date   DATE          NOT NULL,
  status          ENUM('issued','acknowledged','completed','cancelled') NOT NULL DEFAULT 'issued',
  PRIMARY KEY (referral_id),
  KEY idx_referral_consult (consultation_id),
  KEY idx_referral_date (referral_date),
  CONSTRAINT fk_referral_consult
    FOREIGN KEY (consultation_id) REFERENCES consultation(consultation_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Referral records — escalation decisions from a consultation';

-- ───────────────────────────── 11. medicine_dispensed
CREATE TABLE medicine_dispensed (
  dispense_id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  consultation_id      INT UNSIGNED  NOT NULL,
  medicine_id          INT UNSIGNED  NOT NULL,
  nurse_id             INT UNSIGNED  NOT NULL,
  quantity             SMALLINT UNSIGNED NOT NULL,
  dosage_instructions  VARCHAR(200)  NULL,
  dispensed_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (dispense_id),
  KEY idx_dispense_consult (consultation_id),
  KEY idx_dispense_medicine (medicine_id),
  CONSTRAINT chk_dispense_qty CHECK (quantity > 0),
  CONSTRAINT fk_dispense_consult
    FOREIGN KEY (consultation_id) REFERENCES consultation(consultation_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_dispense_medicine
    FOREIGN KEY (medicine_id) REFERENCES medicine(medicine_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_dispense_nurse
    FOREIGN KEY (nurse_id) REFERENCES nurse_profile(nurse_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Junction: medicines given per consultation';

-- ───────────────────────────── 12. inventory_log
CREATE TABLE inventory_log (
  log_id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  medicine_id     INT UNSIGNED  NOT NULL,
  actioned_by     INT UNSIGNED  NOT NULL,
  action_type     ENUM('dispensed','restock','expired','adjustment') NOT NULL,
  qty_change      SMALLINT      NOT NULL,
  new_stock_level SMALLINT UNSIGNED NOT NULL,
  remarks         VARCHAR(200)  NULL,
  actioned_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (log_id),
  KEY idx_invlog_medicine (medicine_id),
  KEY idx_invlog_user (actioned_by),
  CONSTRAINT fk_invlog_medicine
    FOREIGN KEY (medicine_id) REFERENCES medicine(medicine_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_invlog_user
    FOREIGN KEY (actioned_by) REFERENCES user_account(user_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Append-only ledger of every stock change event';

-- ───────────────────────────── 13. ape_record
CREATE TABLE ape_record (
  ape_id       INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  employee_id  INT UNSIGNED    NOT NULL,
  nurse_id     INT UNSIGNED    NOT NULL,
  exam_year    YEAR            NOT NULL,
  exam_date    DATE            NOT NULL,
  bp_systolic  SMALLINT        NULL,
  bp_diastolic SMALLINT        NULL,
  weight_kg    DECIMAL(5,2)    NULL,
  height_cm    DECIMAL(5,1)    NULL,
  bmi          DECIMAL(4,2)    NULL,
  status       ENUM('pending','completed','cleared','flagged') NOT NULL DEFAULT 'pending',
  remarks      TEXT            NULL,
  PRIMARY KEY (ape_id),
  UNIQUE KEY uq_ape_employee_year (employee_id, exam_year),
  KEY idx_ape_nurse (nurse_id),
  CONSTRAINT fk_ape_employee
    FOREIGN KEY (employee_id) REFERENCES employee(employee_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_ape_nurse
    FOREIGN KEY (nurse_id) REFERENCES nurse_profile(nurse_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Annual Physical Exam results — one per employee per year';

-- ───────────────────────────── 14. audit_log
CREATE TABLE audit_log (
  audit_id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id          INT UNSIGNED    NULL,
  admin_id         INT UNSIGNED    NULL,
  table_affected   VARCHAR(60)     NOT NULL,
  action_type      ENUM('INSERT','UPDATE','DELETE') NOT NULL,
  record_id        INT UNSIGNED    NOT NULL,
  old_value        JSON            NULL,
  new_value        JSON            NULL,
  ip_address       VARCHAR(45)     NULL,
  action_timestamp DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (audit_id),
  KEY idx_audit_user (user_id),
  KEY idx_audit_admin (admin_id),
  KEY idx_audit_table (table_affected),
  KEY idx_audit_ts (action_timestamp),
  CONSTRAINT fk_audit_user
    FOREIGN KEY (user_id) REFERENCES user_account(user_id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_audit_admin
    FOREIGN KEY (admin_id) REFERENCES admin_account(admin_id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='System-wide immutable audit trail — populated by triggers only';

-- ───────────────────────────── 15. totp_backup_codes
CREATE TABLE totp_backup_codes (
  code_id    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED  NULL,
  admin_id   INT UNSIGNED  NULL,
  code_hash  VARCHAR(255)  NOT NULL,
  is_used    TINYINT(1)    NOT NULL DEFAULT 0,
  created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  used_at    DATETIME      NULL,
  PRIMARY KEY (code_id),
  KEY idx_backup_user (user_id),
  KEY idx_backup_admin (admin_id),
  CONSTRAINT fk_backup_user
    FOREIGN KEY (user_id) REFERENCES user_account(user_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_backup_admin
    FOREIGN KEY (admin_id) REFERENCES admin_account(admin_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='One-time TOTP backup recovery codes — bcrypt hashed';

SET FOREIGN_KEY_CHECKS = 1;
