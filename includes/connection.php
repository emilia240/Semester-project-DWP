<?php
/*
connection.php  =  opens the database connection with PDO and puts it in $db.
Every page that needs the database uses this same $db.

PDO = "PHP Data Objects". One way of talking to the database that works for
MySQL/MariaDB (and others). Its big advantage: PREPARED STATEMENTS.
*/

// DSN = Data Source Name = "where is the database and how do I talk to it".
//   charset=utf8mb4 -> the connection speaks real UTF-8, same as the tables
$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

try {
    $db = new PDO($dsn, DB_USER, DB_PASS, [
        // If a query fails, THROW an exception (so try/catch can see it) instead of failing silently.
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        // fetch() gives ['Username' => 'mira'] and not also [0 => 'mira'] (half the size, easier to read).
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // false = the DATABASE prepares the statement (real prepared statements).
        // That is the proper protection against SQL injection. true would only imitate it in PHP.
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $error) {
    // NEVER echo $error->getMessage() to visitors: it can contain the host and the user name.
    // We write it to the server log and show a boring message instead.
    error_log('DB connection failed: ' . $error->getMessage());
    http_response_code(500);
    die('Sorry, the database is not available right now.' . (DEBUG ? ' (' . $error->getMessage() . ')' : ''));
}
