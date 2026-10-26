<?php
/*
init.php  =  "the first thing every page loads".
Instead of repeating the setup on every page, each page writes ONE line:
        require_once 'includes/init.php';
and this file loads the rest in the right order.
Right now it loads two things (more are added in later commits):
  1. constants.php   settings + secrets (NOT on GitHub)
  2. connection.php  makes $db (the PDO database connection)
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
