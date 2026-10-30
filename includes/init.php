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
  6. an autoloader, so classes in controllers/ and models/ load themselves
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

/*
Autoloader: when the code says  new LoginController(...)  or  new UserModel(...)  and PHP does not know the class yet,
it calls this function, which looks for  controllers/<ClassName>.php  and  models/<ClassName>.php.
So we never write a long list of require lines. The class names come from the route table in index.php
(written by us), never from the visitor, so this cannot be used to load other files.
*/
spl_autoload_register(function (string $class): void {
    foreach (['controllers', 'models'] as $folder) {
        $file = ROOT_PATH . '/' . $folder . '/' . $class . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});
