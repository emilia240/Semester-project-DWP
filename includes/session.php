<?php
/*
session.php  =  starts the session safely.

PHP is STATELESS: after every page the server forgets who you are.
A SESSION fixes that: the server keeps a small "drawer" ($_SESSION) per visitor,
and the browser carries only a random ID (the cookie PHPSESSID) to find the drawer again.
So the cookie is the key to the account -> we must protect it well.
*/

// ---------------------------------------------------------------------
// 2) Session cookie settings (must be done BEFORE session_start)
// ---------------------------------------------------------------------
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

ini_set('session.use_strict_mode', '1');      // the server refuses session IDs it did not create itself
ini_set('session.use_only_cookies', '1');     // never accept ?PHPSESSID=... in the URL
session_set_cookie_params([
    'lifetime' => 0,                // 0 = cookie disappears when the browser is closed
    'path'     => '/',
    'httponly' => true,             // JavaScript cannot read the cookie (limits XSS damage)
    'samesite' => 'Lax',            // the cookie is not sent on cross-site POSTs (helps against CSRF)
    'secure'   => $isHttps,         // on https the cookie is only sent over https
]);

session_start();


/*
destroy_session()  =  a REAL logout. Three things must all happen:
  1) empty the drawer on the server      ($_SESSION = [])
  2) delete the cookie in the browser    (setcookie with a date in the past)
  3) destroy the session on the server   (session_destroy)
Then we start a fresh empty session so we can still show a "You are logged out" message.
*/
function destroy_session(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $c = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $c['path'],
            'domain'   => $c['domain'],
            'secure'   => $c['secure'],
            'httponly' => $c['httponly'],
            'samesite' => $c['samesite'],
        ]);
    }

    session_destroy();

    // fresh, empty session (new random ID)
    session_start();
}
