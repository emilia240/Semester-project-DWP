<?php
/*
session.php  =  security headers + starts the session.

PHP is STATELESS: after every page the server forgets who you are.
A SESSION fixes that: the server keeps a small "drawer" ($_SESSION) per visitor,
and the browser carries only a random ID (the cookie PHPSESSID) to find the drawer again.
So the cookie is the key to the account -> we must protect it well.
*/

// ---------------------------------------------------------------------
// 1) Security headers: instructions to the browser.
//    ("header: announce the browser where you are" - headers MUST be sent before any HTML.)
// ---------------------------------------------------------------------
header('X-Content-Type-Options: nosniff');          // browser must not guess file types
header('X-Frame-Options: DENY');                    // nobody may put our site in an <iframe> (clickjacking)
header('Referrer-Policy: same-origin');             // do not tell other sites which page you came from
// Content-Security-Policy: allow our own resources and no inline scripts.
// Turnstile needs an exception for Cloudflare's script and its captcha frame.
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://challenges.cloudflare.com; frame-src https://challenges.cloudflare.com; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");

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

// ---------------------------------------------------------------------
// 3) Idle timeout: no activity for 2 hours -> log out.
// ---------------------------------------------------------------------
if (isset($_SESSION['last_seen']) && (time() - $_SESSION['last_seen']) > SESSION_IDLE_SECONDS) {
    destroy_session();   // defined below
}
$_SESSION['last_seen'] = time();


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
    $_SESSION['last_seen'] = time();
}
