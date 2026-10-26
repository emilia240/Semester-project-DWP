<?php
/*
Copy this file to constants.php and fill in YOUR values.
constants.php is in .gitignore, so passwords never go to GitHub.
This example file IS committed, so the teacher (and future me) can see
which settings exist.
*/

// ---- Database ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'dwp_semester');
define('DB_USER', 'root');          // XAMPP default. On Simply: the user from the control panel
define('DB_PASS', '');              // XAMPP default is empty. On Simply: your database password

// ---- Site ----
define('DEBUG', true);                   // true = show PHP errors on screen. MUST be false on the live server
define('TIMEZONE', 'Europe/Copenhagen');
define('SITE_NAME', 'To Be Human');
// Used to build the links inside e-mails. NO slash at the end.
// Local:  http://localhost/semester-project      Live: https://your-domain.dk
define('BASE_URL', 'http://localhost/Semester-project-DWP');

// ---- Rules ----
define('MIN_AGE', 13);                  // youngest allowed age at sign-up
define('VERIFY_TOKEN_HOURS', 24);       // how long the confirmation link works

// ---- E-mail ----
// 'log'  = do NOT send. Write the e-mail to logs/mail.log (use this on your own computer)
// 'smtp' = really send through an SMTP server (use this on the live server)
define('MAIL_MODE', 'log');

// Simply.com: websmtp.simply.com, port 587, STARTTLS.
// It only accepts scripts running on Simply's own web servers (not your laptop).
// Username = a mailbox you made in Simply's Mail Administration (full address).
define('SMTP_HOST', 'websmtp.simply.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'noreply@your-domain.dk');
define('SMTP_PASS', 'CHANGE_ME');
define('MAIL_FROM', 'noreply@your-domain.dk');
define('MAIL_FROM_NAME', SITE_NAME);
