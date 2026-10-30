<?php
/*
init.php  =  "the first thing every page loads".
Instead of writing 4 require lines on every page, each page writes ONE:
        require_once 'includes/init.php';
and this file loads the rest in the right order:
  1. constants.php   settings + secrets (NOT on GitHub)
  2. connection.php  makes $db (the PDO database connection)
  3. session.php     starts the session + security headers
  4. functions.php   helpers (e(), redirect_to(), csrf, login checks ...)
  5. view.php        helpers for the HTML side (url(), render(), partial() ...)
The order matters: session.php uses constants, functions.php uses $db.
*/

define('ROOT_PATH', dirname(__DIR__));   // full path of the project folder

if (!file_exists(__DIR__ . '/constants.php')) {
    // constants.php is not on GitHub, so a fresh copy of the project does not have it.
    die('Missing includes/constants.php - copy includes/constants.example.php to includes/constants.php and fill it in.');
}
require_once __DIR__ . '/constants.php';

date_default_timezone_set(TIMEZONE);

// Errors: while developing we WANT to see them. On the live server we must NOT:
// an error message can show file paths, SQL, even the database user name.
if (DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', ROOT_PATH . '/logs/php_error.log');
}

require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/view.php';
