-- ============================================================
--  NIGHTINGALE — 07 · Demo patient account
--  Run AFTER 06_seed_5_years.sql.
--
--  Adds a fixed-ID patient account so the README's demo
--  credentials match what is in the database.
--    username: patient    password: password
--
--  AUTO_INCREMENT for employee/user_account is at 254 by this
--  point, so we use those IDs.
-- ============================================================

USE nightingale_cms;

SET @current_user_id  = NULL;
SET @current_admin_id = NULL;

INSERT INTO employee
  (employee_id, employee_no, first_name, last_name, birthdate, gender,
   blood_type, allergies, emergency_contact, emergency_phone, dept_id, hire_date)
VALUES
  (254, 'EMP-D0001', 'Demo', 'Patient', '1992-04-18', 'F', 'O+',
   NULL, 'Family Contact', '0917-000-0001', 1, '2020-01-15');

INSERT INTO user_account
  (user_id, employee_id, username, email, password_hash, role,
   totp_enabled, is_active)
VALUES
  (254, 254, 'patient', 'patient@nightingale.clinic',
   '$2y$12$tL6xGk2/Iit2SSQZVuvDJejHH64K5D7dthslXi8b/UZuTeP8oz75O',
   'patient', 0, 1);

-- Resume AUTO_INCREMENT past the demo row.
ALTER TABLE employee     AUTO_INCREMENT = 255;
ALTER TABLE user_account AUTO_INCREMENT = 255;
