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
        $stmt = $db->prepare(
            'SELECT u.UserID, u.Username, u.FirstName, u.LastName, u.Email, u.RoleID, r.RoleName,
                    u.EmailVerifiedAt, u.IsBanned, u.BannedUntil, u.Theme
               FROM `User` u
               JOIN `Role` r ON r.RoleID = u.RoleID
              WHERE u.UserID = :id
              LIMIT 1'
        );
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

/*
confirm_logged_in() = put this at the top of every page that is for members only.
If nobody is logged in, the visitor is sent to the login page and the page stops there.
(In a later commit it gets stricter: it also re-checks the database for bans.)
Returns the user row so the page can use it.
*/
function confirm_logged_in(): array
{
    if (!logged_in()) {
        flash_set('error', 'Please log in to see that page.');
        redirect_to('login.php');
    }

    $user = current_user();

    if ($user === null) {        // the account was deleted while the session was still alive
        destroy_session();
        flash_set('error', 'Your session ended. Please log in again.');
        redirect_to('login.php');
    }
    return $user;
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

