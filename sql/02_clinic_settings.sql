-- ============================================================
--  NIGHTINGALE — 02 · Application-side tables
--  Tables that the dashboards read/write but were not in the
--  original Reference §06 schema.  Run AFTER 01_schema.sql.
-- ============================================================

USE nightingale_cms;

-- ───────────────────────────── clinic_settings (key/value)
-- Drives the "Clinic Settings" card on the admin dashboard.
CREATE TABLE clinic_settings (
  setting_key   VARCHAR(60)  NOT NULL PRIMARY KEY,
  setting_value TEXT         NOT NULL,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Clinic-wide key/value settings';

INSERT INTO clinic_settings (setting_key, setting_value) VALUES
  ('clinic_name',            'Nightingale — Pasig City'),
  ('clinic_address',         '123 Kapasigan Ave, Pasig 1119'),
  ('opening_time',           '08:00'),
  ('closing_time',           '17:00'),
  ('contact_email',          'info@nightingale-pg.ph'),
  ('require_2fa_all_users',  '1'),
  ('session_timeout_minutes','480'),
  ('audit_logging_enabled',  '1');

-- ───────────────────────────── password_reset_tokens
-- Backs the "Forgot password?" email-link flow.
CREATE TABLE password_reset_tokens (
  token_id    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED  NULL,
  admin_id    INT UNSIGNED  NULL,
  token_hash  VARCHAR(255)  NOT NULL,
  expires_at  DATETIME      NOT NULL,
  used_at     DATETIME      NULL,
  ip_address  VARCHAR(45)   NULL,
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (token_id),
  UNIQUE KEY uq_token_hash (token_hash),
  KEY idx_reset_user  (user_id),
  KEY idx_reset_admin (admin_id),
  CONSTRAINT fk_reset_user
    FOREIGN KEY (user_id)  REFERENCES user_account(user_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_reset_admin
    FOREIGN KEY (admin_id) REFERENCES admin_account(admin_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Single-use, time-limited password-reset tokens';

-- ───────────────────────────── login_attempts (rate-limit + auditing)
CREATE TABLE login_attempts (
  attempt_id    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(60)     NOT NULL,
  portal        ENUM('user','admin') NOT NULL,
  ip_address    VARCHAR(45)     NULL,
  user_agent    VARCHAR(255)    NULL,
  succeeded     TINYINT(1)      NOT NULL,
  attempted_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (attempt_id),
  KEY idx_attempts_username (username),
  KEY idx_attempts_ip (ip_address),
  KEY idx_attempts_ts (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Login attempts — used for rate-limiting and forensics';

-- ───────────────────────────── reporting helpers
-- Speeds up admin "consultations by department" / month queries
ALTER TABLE consultation
  ADD INDEX idx_consult_date_employee (consult_date, employee_id);

CREATE OR REPLACE VIEW v_consultation_full AS
SELECT
  c.consultation_id,
  c.consult_date,
  c.case_type,
  c.diagnosis,
  c.work_status,
  c.time_start,
  c.time_end,
  e.employee_id,
  e.employee_no,
  e.first_name,
  e.last_name,
  e.dept_id,
  d.dept_name,
  c.nurse_id,
  vs.bp_systolic,
  vs.bp_diastolic,
  vs.temperature,
  vs.pulse_rate,
  vs.resp_rate,
  vs.o2_saturation,
  vs.weight_kg
FROM consultation c
JOIN employee   e ON e.employee_id = c.employee_id
JOIN department d ON d.dept_id     = e.dept_id
LEFT JOIN vital_signs vs ON vs.consultation_id = c.consultation_id;
