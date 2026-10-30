<?php
/*
functions.php  =  small helpers used by many pages.
A function is written ONCE here and called everywhere (no copy-paste).
*/

// =====================================================================
// A. OUTPUT + REDIRECT
// =====================================================================

/*
e() = "escape". Use it on EVERYTHING that came from a user before it goes into HTML.
  <script>alert(1)</script>  becomes  &lt;script&gt;alert(1)&lt;/script&gt;  -> shown as text, not run.
That is the protection against XSS (Cross-Site Scripting).
*/
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/*
redirect_to() = send the browser to another page.
header('Location: ...') only ASKS the browser to go there; PHP keeps running unless we exit.
So exit is not optional: without it the rest of the page still runs.
*/
function redirect_to(string $location): void
{
    header('Location: ' . $location);
    exit;
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/*
post_str() = read one text field from $_POST, always as a STRING.
Why: an attacker can send  username[]=x  and then $_POST['username'] is an ARRAY,
and trim(array) crashes PHP 8. This returns '' instead.  (?? '' also avoids "undefined key" warnings.)
*/
function post_str(string $name): string
{
    $v = $_POST[$name] ?? '';
    return is_string($v) ? trim($v) : '';
}

// Passwords are NOT trimmed (spaces may be part of the password).
function post_raw(string $name): string
{
    $v = $_POST[$name] ?? '';
    return is_string($v) ? $v : '';
}

// =====================================================================
// B. FLASH MESSAGES  ("Account created!" shown once, after a redirect)
// =====================================================================
// Pattern: POST -> do the work -> flash_set() -> redirect -> GET shows the message.
// (Called Post/Redirect/Get. It stops the "resubmit the form?" popup on refresh.)

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get_all(): array
{
    $all = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);      // show only once
    return $all;
}

// =====================================================================
// C. CSRF PROTECTION
// =====================================================================
/*
CSRF = another website tricks YOUR logged-in browser into sending a request to OUR site
(the browser adds your cookie automatically!). Defence: every form carries a secret random
token that only our own pages know. The other site cannot guess it.
 1) csrf_token()  makes/keeps one random token in the session
 2) csrf_field()  prints it as a hidden field in every <form>
 3) csrf_check()  on POST: does the submitted token match the one in the session?
hash_equals() compares in constant time (a normal === can leak information through timing).
*/
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));   // 64 hex characters, cryptographically secure
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_check(): bool
{
    $sent = $_POST['csrf_token'] ?? '';
    $real = $_SESSION['csrf_token'] ?? '';
    return is_string($sent) && $real !== '' && hash_equals($real, $sent);
}

// =====================================================================
// D. LOGIN STATE
// =====================================================================

// Is there a user id in the session? (Cheap check, does NOT ask the database.)
function logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

/*
current_user() = the full, FRESH user row from the database (or null).
We ask the database every time instead of trusting what we stored at login, because
things can change while someone is logged in: an admin bans them, the account is deleted ...
static = remember the answer during this one page load so we only query once.
*/
function current_user(): ?array
{
    global $db;
    static $user = false;

    if (!logged_in()) {
        return null;
    }
    if ($user === false) {
        $user = (new UserModel($db))->findWithRole((int)$_SESSION['user_id']);
    }
    return $user;
}

// Is this user banned RIGHT NOW? (permanent: BannedUntil is NULL, temporary: date in the future)
function is_banned(array $user): bool
{
    if ((int)$user['IsBanned'] !== 1) {
        return false;
    }
    return $user['BannedUntil'] === null || strtotime($user['BannedUntil']) > time();
}

/*
confirm_logged_in() = put this at the top of every page that is for members only.
It does more than "is there a session?":
  - the user must still exist
  - the e-mail must be confirmed
  - the user must not be banned
If anything is wrong the session is destroyed and the visitor goes to the login page.
Returns the user row so the page can use it.
*/
function confirm_logged_in(): array
{
    if (!logged_in()) {
        flash_set('error', 'Please log in to see that page.');
        redirect_to(url('login'));
    }

    $user = current_user();

    if ($user === null || $user['EmailVerifiedAt'] === null || is_banned($user)) {
        destroy_session();
        flash_set('error', 'Your session ended. Please log in again.');
        redirect_to(url('login'));
    }
    return $user;
}

// Same, but the user must also have the role "admin". Used later by the admin area.
function confirm_admin(): array
{
    $user = confirm_logged_in();
    if ($user['RoleName'] !== 'admin') {
        http_response_code(403);        // 403 = Forbidden
        die('403 - You do not have access to this page.');
    }
    return $user;
}

// =====================================================================
// E. LOGIN THROTTLING (slows down password guessing)
// =====================================================================
// Simple version: counts failures in the SESSION. A bot that throws its cookie away
// gets around it, so a later improvement is to count per IP/email in the database.
const MAX_LOGIN_FAILURES = 5;
const LOGIN_LOCK_SECONDS = 60;

function login_is_locked(): bool
{
    return isset($_SESSION['login_locked_until']) && $_SESSION['login_locked_until'] > time();
}

function login_register_failure(): void
{
    $_SESSION['login_failures'] = ($_SESSION['login_failures'] ?? 0) + 1;
    if ($_SESSION['login_failures'] >= MAX_LOGIN_FAILURES) {
        $_SESSION['login_locked_until'] = time() + LOGIN_LOCK_SECONDS;
        $_SESSION['login_failures']     = 0;
    }
}

function login_clear_failures(): void
{
    unset($_SESSION['login_failures'], $_SESSION['login_locked_until']);
}

// =====================================================================
// F. VALIDATION HELPERS (used by the sign-up form)
// =====================================================================
// Rule: validate on the SERVER. HTML attributes like required/minlength can be removed
// with the browser's dev tools, so they are only a convenience for honest users.

// Returns an error text, or '' if the e-mail is fine.
function validate_email(string $email): string
{
    if ($email === '' || mb_strlen($email) > 190) {
        return 'Please enter a valid e-mail address.';
    }
    // filter_var is the built-in "is this an e-mail?" check (teacher: filter_var or a regular expression).
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return 'Please enter a valid e-mail address.';
    }
    return '';
}

function validate_username(string $username): string
{
    // 3-30 characters: letters, digits, underscore. No spaces, no @, no emoji -> safe in URLs and e-mails.
    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
        return 'Username: 3-30 characters, only letters, numbers and underscore (_).';
    }
    $reserved = ['admin', 'administrator', 'root', 'system', 'support', 'moderator', 'mod', 'staff', 'help', 'official', 'tobehuman', 'humanmade', 'null'];
    if (in_array(strtolower($username), $reserved, true)) {
        return 'That username is reserved. Please choose another.';
    }
    return '';
}

// Real names: letters from any language (æøå, é ...), spaces, hyphen, apostrophe.
function validate_person_name(string $name, string $label): string
{
    if ($name === '' || mb_strlen($name) > 80 || !preg_match("/^[\p{L}][\p{L} '\-]*$/u", $name)) {
        return $label . ': letters only (spaces, - and \' allowed), max 80 characters.';
    }
    return '';
}

function validate_password(string $password, string $username, string $email): string
{
    // bcrypt only reads the first 72 BYTES, so a longer password would be silently cut. Refuse instead.
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (strlen($password) > 72) {
        return 'Password can be at most 72 characters.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'Password must contain at least one letter and one number.';
    }
    if (strcasecmp($password, $username) === 0 || strcasecmp($password, $email) === 0) {
        return 'Password cannot be the same as your username or e-mail.';
    }
    return '';
}

// Returns a DateTime if $s is a REAL date written YYYY-MM-DD (e.g. rejects 2001-02-30), otherwise null.
function parse_birthdate(string $s): ?DateTime
{
    $d = DateTime::createFromFormat('!Y-m-d', $s);
    $errors = DateTime::getLastErrors();
    if ($d === false || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return null;
    }
    return $d;
}

function validate_birthdate(string $s): string
{
    $d = parse_birthdate($s);
    if ($d === null) {
        return 'Please enter your birthdate.';
    }
    $now = new DateTime('today');
    if ($d > $now) {
        return 'Your birthdate cannot be in the future.';
    }
    $age = $d->diff($now)->y;           // age in whole years
    if ($age < MIN_AGE) {
        return 'You must be at least ' . MIN_AGE . ' years old to join.';
    }
    if ($age > 120) {
        return 'Please check your birthdate.';
    }
    return '';
}

// =====================================================================
// G. E-MAILS WITH LINKS (the tokens themselves live in models/TokenModel.php)
// =====================================================================

// The e-mail sent after sign-up and on "resend".
function send_verification_email(string $to, string $username, string $rawToken): bool
{
    require_once __DIR__ . '/mailer.php';

    $link = BASE_URL . '/' . url('verify', ['token' => $rawToken]);
    $body = "Hi $username,\n\n"
          . "Welcome to " . SITE_NAME . "! Please confirm your e-mail address by opening this link:\n\n"
          . "$link\n\n"
          . "The link works for " . VERIFY_TOKEN_HOURS . " hours.\n"
          . "If you did not sign up, just ignore this e-mail - nothing will happen.\n";

    return send_mail($to, 'Confirm your e-mail for ' . SITE_NAME, $body);
}

// The e-mail sent by "Forgot password" (AuthController::forgot). The link opens the reset page.
function send_reset_email(string $to, string $username, string $rawToken): bool
{
    require_once __DIR__ . '/mailer.php';

    $link = BASE_URL . '/' . url('reset', ['token' => $rawToken]);
    $body = "Hi $username,\n\n"
          . "Someone (hopefully you) asked to reset the password of your " . SITE_NAME . " account.\n"
          . "Open this link to choose a new password:\n\n"
          . "$link\n\n"
          . "The link works for " . RESET_TOKEN_HOURS . " hour(s) and only once.\n"
          . "If this was not you, ignore this e-mail - your password stays exactly as it is.\n";

    return send_mail($to, 'Reset your password for ' . SITE_NAME, $body);
}

// A heads-up after the password was changed. If it was NOT the owner, they find out at once.
function send_password_changed_email(string $to, string $username): bool
{
    require_once __DIR__ . '/mailer.php';

    $body = "Hi $username,\n\n"
          . "The password of your " . SITE_NAME . " account was just changed.\n"
          . "If that was you, nothing more to do.\n"
          . "If it was NOT you, reset your password right away: " . BASE_URL . '/' . url('forgot') . "\n";

    return send_mail($to, 'Your password was changed - ' . SITE_NAME, $body);
}

// Fake bcrypt hash. When a login fails because the e-mail does not exist, we still run password_verify
// against THIS, so "unknown e-mail" takes as long as "wrong password" and nobody can tell them apart by timing.
const DUMMY_HASH = '$2y$10$ownSu4Lj409zKzKWSjbbvuBALyNQHhdUbttFfE3z/tLDgygtWVeom';
