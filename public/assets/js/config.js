/* ============================================================
   NIGHTINGALE — Frontend Config
   --------------------------------------------------------------
   Pure client-side configuration.  All authentication,
   user data, and TOTP verification now happens server-side.
   No credentials are stored in this file.
============================================================ */

window.APP_CONFIG = Object.freeze({
  brand:    'Nightingale',
  tagline:  'Clinic Monitoring System',

  /** API base — relative to public/.  Override if you mount the
      project on a non-default subdirectory. */
  apiBase: 'api',

  endpoints: {
    login:           'auth/login.php',
    verifyTotp:      'auth/verify-totp.php',
    adminLogin:      'auth/admin-login.php',
    adminVerifyTotp: 'auth/admin-verify-totp.php',
    totpSetup:       'auth/totp-setup.php',
    logout:          'auth/logout.php',
    session:         'auth/session.php',
    passwordRequest: 'auth/password-request.php',
    passwordReset:   'auth/password-reset.php',
    queue:           'queue.php',
    consultation:    'consultation.php',
    vitals:          'vitals.php',
    medicines:       'medicines.php',
    dispense:        'dispense.php',
    restock:         'restock.php',
    referral:        'referral.php',
    ape:             'ape.php',
    patients:        'patients.php',
    reports:         'reports.php',
    audit:           'audit.php',
    settings:        'settings.php',
  },

  roleRoutes: {
    patient: 'patient.php',
    nurse:   'nurse.php',
    admin:   'admin.php',
  },

  /** Redux-light: client-only soft session indicator (true source
      of truth lives in the PHP session cookie). */
  sessionStorageKey: 'nightingale_client_session',
});
