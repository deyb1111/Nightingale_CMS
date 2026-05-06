-- ============================================================
--  NIGHTINGALE — 04 · Security (least-privilege DB users)
--  Run AFTER 03_storedprocs_triggers.sql.
--
--  cms_app_user — used by the PHP application
--  cms_readonly — used for ad-hoc reporting only
--
--  IMPORTANT: change both passwords in your `.env` and re-run
--  this script in production.
-- ============================================================

DROP USER IF EXISTS 'cms_app_user'@'localhost';
DROP USER IF EXISTS 'cms_readonly'@'localhost';
DROP USER IF EXISTS 'cms_app_user'@'%';
DROP USER IF EXISTS 'cms_readonly'@'%';

CREATE USER 'cms_app_user'@'localhost' IDENTIFIED BY 'Cms@AppUser2026!';
CREATE USER 'cms_app_user'@'%'         IDENTIFIED BY 'Cms@AppUser2026!';

-- Per-table grants — the application user CANNOT drop, alter,
-- or create tables.
GRANT SELECT, INSERT, UPDATE         ON nightingale_cms.department          TO 'cms_app_user'@'localhost';
GRANT SELECT, INSERT, UPDATE         ON nightingale_cms.employee            TO 'cms_app_user'@'localhost';
GRANT SELECT, INSERT, UPDATE         ON nightingale_cms.user_account        TO 'cms_app_user'@'localhost';
GRANT SELECT, INSERT, UPDATE         ON nightingale_cms.admin_account       TO 'cms_app_user'@'localhost';
GRANT SELECT, INSERT, UPDATE         ON nightingale_cms.nurse_profile       TO 'cms_app_user'@'localhost';
GRANT SELECT, INSERT, UPDATE         ON nightingale_cms.queue               TO 'cms_app_user'@'localhost';
GRANT SELECT, INSERT, UPDATE         ON nightingale_cms.consultation        TO 'cms_app_user'@'localhost';
GRANT SELECT, INSERT                 ON nightingale_cms.vital_signs         TO 'cms_app_user'@'localhost';
GRANT SELECT, INSERT, UPDATE         ON nightingale_cms.referral            TO 'cms_app_user'@'localhost';
GRANT SELECT, UPDATE                 ON nightingale_cms.medicine            TO 'cms_app_user'@'localhost';
GRANT SELECT, INSERT                 ON nightingale_cms.medicine_dispensed  TO 'cms_app_user'@'localhost';
GRANT SELECT, INSERT                 ON nightingale_cms.inventory_log       TO 'cms_app_user'@'localhost';
GRANT SELECT, INSERT, UPDATE         ON nightingale_cms.ape_record          TO 'cms_app_user'@'localhost';
GRANT SELECT                         ON nightingale_cms.audit_log           TO 'cms_app_user'@'localhost';
GRANT SELECT, INSERT, UPDATE         ON nightingale_cms.totp_backup_codes   TO 'cms_app_user'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON nightingale_cms.password_reset_tokens TO 'cms_app_user'@'localhost';
GRANT SELECT, INSERT                 ON nightingale_cms.login_attempts      TO 'cms_app_user'@'localhost';
GRANT SELECT, INSERT, UPDATE         ON nightingale_cms.clinic_settings     TO 'cms_app_user'@'localhost';
GRANT SELECT                         ON nightingale_cms.v_consultation_full TO 'cms_app_user'@'localhost';
GRANT EXECUTE                        ON nightingale_cms.*                   TO 'cms_app_user'@'localhost';

-- Mirror grants for the wildcard host (keeps remote DB hosts working).
GRANT SELECT, INSERT, UPDATE         ON nightingale_cms.department          TO 'cms_app_user'@'%';
GRANT SELECT, INSERT, UPDATE         ON nightingale_cms.employee            TO 'cms_app_user'@'%';
GRANT SELECT, INSERT, UPDATE         ON nightingale_cms.user_account        TO 'cms_app_user'@'%';
GRANT SELECT, INSERT, UPDATE         ON nightingale_cms.admin_account       TO 'cms_app_user'@'%';
GRANT SELECT, INSERT, UPDATE         ON nightingale_cms.nurse_profile       TO 'cms_app_user'@'%';
GRANT SELECT, INSERT, UPDATE         ON nightingale_cms.queue               TO 'cms_app_user'@'%';
GRANT SELECT, INSERT, UPDATE         ON nightingale_cms.consultation        TO 'cms_app_user'@'%';
GRANT SELECT, INSERT                 ON nightingale_cms.vital_signs         TO 'cms_app_user'@'%';
GRANT SELECT, INSERT, UPDATE         ON nightingale_cms.referral            TO 'cms_app_user'@'%';
GRANT SELECT, UPDATE                 ON nightingale_cms.medicine            TO 'cms_app_user'@'%';
GRANT SELECT, INSERT                 ON nightingale_cms.medicine_dispensed  TO 'cms_app_user'@'%';
GRANT SELECT, INSERT                 ON nightingale_cms.inventory_log       TO 'cms_app_user'@'%';
GRANT SELECT, INSERT, UPDATE         ON nightingale_cms.ape_record          TO 'cms_app_user'@'%';
GRANT SELECT                         ON nightingale_cms.audit_log           TO 'cms_app_user'@'%';
GRANT SELECT, INSERT, UPDATE         ON nightingale_cms.totp_backup_codes   TO 'cms_app_user'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON nightingale_cms.password_reset_tokens TO 'cms_app_user'@'%';
GRANT SELECT, INSERT                 ON nightingale_cms.login_attempts      TO 'cms_app_user'@'%';
GRANT SELECT, INSERT, UPDATE         ON nightingale_cms.clinic_settings     TO 'cms_app_user'@'%';
GRANT SELECT                         ON nightingale_cms.v_consultation_full TO 'cms_app_user'@'%';
GRANT EXECUTE                        ON nightingale_cms.*                   TO 'cms_app_user'@'%';

-- READ-ONLY user (for ad-hoc reporting / BI dashboards)
CREATE USER 'cms_readonly'@'localhost' IDENTIFIED BY 'Cms@ReadOnly2026!';
GRANT SELECT ON nightingale_cms.* TO 'cms_readonly'@'localhost';

FLUSH PRIVILEGES;
