-- ============================================================
--  NIGHTINGALE — 03 · Stored Procedures + Audit Triggers
--  Run AFTER 02_clinic_settings.sql.
--
--  Stored procedures encapsulate ACID-critical operations.
--  Triggers populate audit_log automatically — they read the
--  acting user from the session variables @current_user_id /
--  @current_admin_id, which the PHP layer SETs at the start of
--  every authenticated request.
-- ============================================================

USE nightingale_cms;

DELIMITER $$

-- ─── SP 1: dispense medicine (ACID) ──────────────────────────
DROP PROCEDURE IF EXISTS sp_dispense_medicine$$
CREATE PROCEDURE sp_dispense_medicine(
  IN  p_consultation_id INT UNSIGNED,
  IN  p_medicine_id     INT UNSIGNED,
  IN  p_nurse_id        INT UNSIGNED,
  IN  p_user_id         INT UNSIGNED,
  IN  p_quantity        SMALLINT UNSIGNED,
  IN  p_instructions    VARCHAR(200)
)
BEGIN
  DECLARE v_current_stock SMALLINT UNSIGNED DEFAULT 0;
  DECLARE v_new_stock     SMALLINT UNSIGNED DEFAULT 0;
  DECLARE v_medicine_name VARCHAR(120) DEFAULT '';
  DECLARE v_error_msg     VARCHAR(255) DEFAULT '';

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  START TRANSACTION;

    -- Lock the row to serialize concurrent dispensers
    SELECT current_stock, medicine_name
      INTO v_current_stock, v_medicine_name
      FROM medicine
      WHERE medicine_id = p_medicine_id
      FOR UPDATE;

    IF v_current_stock < p_quantity THEN
      SET v_error_msg = CONCAT(
        'Insufficient stock for ', v_medicine_name,
        '. Available: ', v_current_stock,
        ', Requested: ', p_quantity);
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_msg;
    END IF;

    SET v_new_stock = v_current_stock - p_quantity;

    UPDATE medicine
      SET current_stock = v_new_stock
      WHERE medicine_id = p_medicine_id;

    INSERT INTO medicine_dispensed
      (consultation_id, medicine_id, nurse_id, quantity, dosage_instructions)
    VALUES
      (p_consultation_id, p_medicine_id, p_nurse_id, p_quantity, p_instructions);

    INSERT INTO inventory_log
      (medicine_id, actioned_by, action_type, qty_change, new_stock_level, remarks)
    VALUES
      (p_medicine_id, p_user_id, 'dispensed', -(p_quantity), v_new_stock,
       CONCAT('Dispensed for consultation #', p_consultation_id));

  COMMIT;
END$$

-- ─── SP 2: register patient ──────────────────────────────────
DROP PROCEDURE IF EXISTS sp_register_patient$$
CREATE PROCEDURE sp_register_patient(
  IN  p_employee_no       VARCHAR(20),
  IN  p_first_name        VARCHAR(60),
  IN  p_last_name         VARCHAR(60),
  IN  p_birthdate         DATE,
  IN  p_gender            ENUM('M','F','Other'),
  IN  p_blood_type        VARCHAR(5),
  IN  p_allergies         TEXT,
  IN  p_emergency_contact VARCHAR(100),
  IN  p_emergency_phone   VARCHAR(20),
  IN  p_dept_id           TINYINT UNSIGNED,
  IN  p_username          VARCHAR(60),
  IN  p_email             VARCHAR(120),
  IN  p_password_hash     VARCHAR(255),
  OUT p_new_employee_id   INT UNSIGNED,
  OUT p_new_user_id       INT UNSIGNED
)
BEGIN
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  START TRANSACTION;
    INSERT INTO employee
      (employee_no, first_name, last_name, birthdate, gender,
       blood_type, allergies, emergency_contact, emergency_phone, dept_id)
    VALUES
      (p_employee_no, p_first_name, p_last_name, p_birthdate, p_gender,
       p_blood_type, p_allergies, p_emergency_contact, p_emergency_phone, p_dept_id);

    SET p_new_employee_id = LAST_INSERT_ID();

    INSERT INTO user_account
      (employee_id, username, email, password_hash, role)
    VALUES
      (p_new_employee_id, p_username, p_email, p_password_hash, 'patient');

    SET p_new_user_id = LAST_INSERT_ID();
  COMMIT;
END$$

-- ─── SP 3: register nurse ────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_register_nurse$$
CREATE PROCEDURE sp_register_nurse(
  IN  p_employee_no    VARCHAR(20),
  IN  p_first_name     VARCHAR(60),
  IN  p_last_name      VARCHAR(60),
  IN  p_birthdate      DATE,
  IN  p_gender         ENUM('M','F','Other'),
  IN  p_dept_id        TINYINT UNSIGNED,
  IN  p_username       VARCHAR(60),
  IN  p_email          VARCHAR(120),
  IN  p_password_hash  VARCHAR(255),
  IN  p_license_no     VARCHAR(30),
  IN  p_license_expiry DATE,
  IN  p_specialization VARCHAR(80),
  OUT p_new_nurse_id   INT UNSIGNED
)
BEGIN
  DECLARE v_employee_id INT UNSIGNED;
  DECLARE v_user_id     INT UNSIGNED;

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  START TRANSACTION;
    INSERT INTO employee
      (employee_no, first_name, last_name, birthdate, gender, dept_id)
    VALUES
      (p_employee_no, p_first_name, p_last_name, p_birthdate, p_gender, p_dept_id);
    SET v_employee_id = LAST_INSERT_ID();

    INSERT INTO user_account
      (employee_id, username, email, password_hash, role)
    VALUES
      (v_employee_id, p_username, p_email, p_password_hash, 'nurse');
    SET v_user_id = LAST_INSERT_ID();

    INSERT INTO nurse_profile
      (user_id, license_no, license_expiry, specialization)
    VALUES
      (v_user_id, p_license_no, p_license_expiry, p_specialization);
    SET p_new_nurse_id = LAST_INSERT_ID();
  COMMIT;
END$$

-- ─── SP 4: open consultation ─────────────────────────────────
DROP PROCEDURE IF EXISTS sp_open_consultation$$
CREATE PROCEDURE sp_open_consultation(
  IN  p_employee_id     INT UNSIGNED,
  IN  p_nurse_id        INT UNSIGNED,
  IN  p_queue_number    SMALLINT UNSIGNED,
  IN  p_chief_complaint TEXT,
  IN  p_case_type       ENUM('illness','injury','follow_up','emergency'),
  OUT p_consultation_id INT UNSIGNED
)
BEGIN
  DECLARE v_queue_id INT UNSIGNED;

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  START TRANSACTION;
    INSERT INTO queue
      (employee_id, queue_number, queue_date, time_in, status)
    VALUES
      (p_employee_id, p_queue_number, CURDATE(), CURTIME(), 'in_progress');
    SET v_queue_id = LAST_INSERT_ID();

    INSERT INTO consultation
      (queue_id, employee_id, nurse_id, chief_complaint,
       case_type, consult_date, time_start)
    VALUES
      (v_queue_id, p_employee_id, p_nurse_id, p_chief_complaint,
       p_case_type, CURDATE(), CURTIME());
    SET p_consultation_id = LAST_INSERT_ID();
  COMMIT;
END$$

-- ─── SP 5: close consultation ────────────────────────────────
DROP PROCEDURE IF EXISTS sp_close_consultation$$
CREATE PROCEDURE sp_close_consultation(
  IN p_consultation_id INT UNSIGNED,
  IN p_work_status     ENUM('fit','light_duty','sick_leave','for_hospitalization'),
  IN p_diagnosis       VARCHAR(200),
  IN p_nurse_notes     TEXT
)
BEGIN
  DECLARE v_queue_id INT UNSIGNED;

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  START TRANSACTION;
    UPDATE consultation
      SET time_end    = CURTIME(),
          work_status = p_work_status,
          diagnosis   = p_diagnosis,
          nurse_notes = p_nurse_notes
      WHERE consultation_id = p_consultation_id;

    SELECT queue_id INTO v_queue_id
      FROM consultation
      WHERE consultation_id = p_consultation_id;

    UPDATE queue
      SET status = 'done'
      WHERE queue_id = v_queue_id;
  COMMIT;
END$$

-- ─── SP 6: restock medicine ──────────────────────────────────
DROP PROCEDURE IF EXISTS sp_restock_medicine$$
CREATE PROCEDURE sp_restock_medicine(
  IN p_medicine_id INT UNSIGNED,
  IN p_user_id     INT UNSIGNED,
  IN p_qty_add     SMALLINT UNSIGNED,
  IN p_remarks     VARCHAR(200)
)
BEGIN
  DECLARE v_new_stock SMALLINT UNSIGNED;

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  START TRANSACTION;
    UPDATE medicine
      SET current_stock = current_stock + p_qty_add
      WHERE medicine_id = p_medicine_id;

    SELECT current_stock INTO v_new_stock
      FROM medicine
      WHERE medicine_id = p_medicine_id;

    INSERT INTO inventory_log
      (medicine_id, actioned_by, action_type, qty_change, new_stock_level, remarks)
    VALUES
      (p_medicine_id, p_user_id, 'restock', p_qty_add, v_new_stock, p_remarks);
  COMMIT;
END$$

DELIMITER ;


-- ============================================================
-- AUDIT TRIGGERS
--   Each fires AFTER {INSERT|UPDATE} and writes a row to
--   audit_log using the session variables set by lib/audit.php:
--     SET @current_user_id  = …;   -- nurse / patient session
--     SET @current_admin_id = …;   -- admin session
-- ============================================================

DELIMITER $$

-- consultation
DROP TRIGGER IF EXISTS trg_audit_consultation_insert$$
CREATE TRIGGER trg_audit_consultation_insert
AFTER INSERT ON consultation
FOR EACH ROW
BEGIN
  INSERT INTO audit_log
    (user_id, admin_id, table_affected, action_type, record_id,
     old_value, new_value, action_timestamp)
  VALUES
    (@current_user_id, @current_admin_id,
     'consultation', 'INSERT', NEW.consultation_id,
     NULL,
     JSON_OBJECT(
       'queue_id',        NEW.queue_id,
       'employee_id',     NEW.employee_id,
       'nurse_id',        NEW.nurse_id,
       'chief_complaint', NEW.chief_complaint,
       'case_type',       NEW.case_type,
       'consult_date',    NEW.consult_date),
     NOW());
END$$

DROP TRIGGER IF EXISTS trg_audit_consultation_update$$
CREATE TRIGGER trg_audit_consultation_update
AFTER UPDATE ON consultation
FOR EACH ROW
BEGIN
  INSERT INTO audit_log
    (user_id, admin_id, table_affected, action_type, record_id,
     old_value, new_value, action_timestamp)
  VALUES
    (@current_user_id, @current_admin_id,
     'consultation', 'UPDATE', NEW.consultation_id,
     JSON_OBJECT(
       'diagnosis',   OLD.diagnosis,
       'work_status', OLD.work_status,
       'nurse_notes', OLD.nurse_notes,
       'time_end',    OLD.time_end),
     JSON_OBJECT(
       'diagnosis',   NEW.diagnosis,
       'work_status', NEW.work_status,
       'nurse_notes', NEW.nurse_notes,
       'time_end',    NEW.time_end),
     NOW());
END$$

-- medicine_dispensed
DROP TRIGGER IF EXISTS trg_audit_medicine_dispensed_insert$$
CREATE TRIGGER trg_audit_medicine_dispensed_insert
AFTER INSERT ON medicine_dispensed
FOR EACH ROW
BEGIN
  INSERT INTO audit_log
    (user_id, admin_id, table_affected, action_type, record_id,
     old_value, new_value, action_timestamp)
  VALUES
    (@current_user_id, @current_admin_id,
     'medicine_dispensed', 'INSERT', NEW.dispense_id,
     NULL,
     JSON_OBJECT(
       'consultation_id',     NEW.consultation_id,
       'medicine_id',         NEW.medicine_id,
       'nurse_id',            NEW.nurse_id,
       'quantity',            NEW.quantity,
       'dosage_instructions', NEW.dosage_instructions),
     NOW());
END$$

-- user_account
DROP TRIGGER IF EXISTS trg_audit_user_update$$
CREATE TRIGGER trg_audit_user_update
AFTER UPDATE ON user_account
FOR EACH ROW
BEGIN
  IF OLD.role <> NEW.role
     OR OLD.totp_enabled <> NEW.totp_enabled
     OR OLD.is_active    <> NEW.is_active
     OR OLD.password_hash <> NEW.password_hash THEN
    INSERT INTO audit_log
      (user_id, admin_id, table_affected, action_type, record_id,
       old_value, new_value, action_timestamp)
    VALUES
      (@current_user_id, @current_admin_id,
       'user_account', 'UPDATE', NEW.user_id,
       JSON_OBJECT(
         'role',         OLD.role,
         'totp_enabled', OLD.totp_enabled,
         'is_active',    OLD.is_active,
         'pw_changed',   OLD.password_hash <> NEW.password_hash),
       JSON_OBJECT(
         'role',         NEW.role,
         'totp_enabled', NEW.totp_enabled,
         'is_active',    NEW.is_active),
       NOW());
  END IF;
END$$

-- medicine stock
DROP TRIGGER IF EXISTS trg_audit_medicine_stock_update$$
CREATE TRIGGER trg_audit_medicine_stock_update
AFTER UPDATE ON medicine
FOR EACH ROW
BEGIN
  IF OLD.current_stock <> NEW.current_stock THEN
    INSERT INTO audit_log
      (user_id, admin_id, table_affected, action_type, record_id,
       old_value, new_value, action_timestamp)
    VALUES
      (@current_user_id, @current_admin_id,
       'medicine', 'UPDATE', NEW.medicine_id,
       JSON_OBJECT('current_stock', OLD.current_stock),
       JSON_OBJECT('current_stock', NEW.current_stock),
       NOW());
  END IF;
END$$

-- referral
DROP TRIGGER IF EXISTS trg_audit_referral_insert$$
CREATE TRIGGER trg_audit_referral_insert
AFTER INSERT ON referral
FOR EACH ROW
BEGIN
  INSERT INTO audit_log
    (user_id, admin_id, table_affected, action_type, record_id,
     old_value, new_value, action_timestamp)
  VALUES
    (@current_user_id, @current_admin_id,
     'referral', 'INSERT', NEW.referral_id,
     NULL,
     JSON_OBJECT(
       'consultation_id', NEW.consultation_id,
       'referral_type',   NEW.referral_type,
       'referred_to',     NEW.referred_to,
       'reason',          NEW.reason),
     NOW());
END$$

DELIMITER ;
