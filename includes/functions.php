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

