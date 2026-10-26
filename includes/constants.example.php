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
