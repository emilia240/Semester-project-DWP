<?php
/*
view.php  =  everything the VIEWS (the HTML files in views/) need.

MVC in this project (see README):
  index.php        front controller: the ONE entrance. Reads ?page=... and picks a controller.
  controllers/     the "heavy lifters": read the form, validate, decide what happens next.
  models/          everything that talks to the database (all SQL lives here).
  views/           what the visitor sees: HTML with as little PHP as possible.
This file holds the helpers that connect controllers and views: url(), render(), partial() ...
*/

require_once __DIR__ . '/icons.php';

/*
url() = the address of a page of OUR site.
  url('login')                          ->  index.php?page=login
  url('verify', ['token' => 'abc'])     ->  index.php?page=verify&token=abc
Always build links with this function (not by hand), so a page name is written in one place only.
*/
function url(string $page, array $query = []): string
{
    return 'index.php?' . http_build_query(['page' => $page] + $query);
}

/*
render() = show a view inside the shared layout.
$vars become normal variables inside the view:  render('auth/login', ['errors' => $errors])  ->  $errors
Layouts:
  'site'  header + footer (normal pages)
  'split' the picture-and-form screen used by log in, join and the password pages
*/
function render(string $view, array $vars = []): void
{
    $vars += ['title' => SITE_NAME, 'layout' => 'site', 'page' => '', 'user' => null];
    extract($vars, EXTR_SKIP);
    $layoutDir = __DIR__ . '/../views/layout/';

    require $layoutDir . 'head.php';
    if ($layout === 'site') {
        require $layoutDir . 'header.php';
    }
    require __DIR__ . '/../views/' . $view . '.php';        // $view is always written by us in a controller, never by a visitor
    if ($layout === 'site') {
        require $layoutDir . 'footer.php';
    }
    require $layoutDir . 'foot.php';
}

/* partial() = include a small reusable piece of HTML (views/partials/...) */
function partial(string $name, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    require __DIR__ . '/../views/partials/' . $name . '.php';
}

// ---------------------------------------------------------------------
// Forms: show the error under a field
// ---------------------------------------------------------------------

/* The red sentence under a field, or '' when that field has no error. $errors comes from the controller. */
function field_error(array $errors, string $name): string
{
    if (!isset($errors[$name])) {
        return '';
    }
    return '<p class="mt-1 text-sm font-semibold text-error" id="error-' . e($name) . '">' . e($errors[$name]) . '</p>';
}

/* Extra attributes for the <input> itself, so a screen reader also knows the field has a problem. */
function field_attrs(array $errors, string $name): string
{
    return isset($errors[$name]) ? ' aria-invalid="true" aria-describedby="error-' . e($name) . '"' : '';
}

/* 'input-error' (a red border) when the field has an error, otherwise nothing. */
function field_class(array $errors, string $name): string
{
    return isset($errors[$name]) ? 'input-error' : '';
}

// ---------------------------------------------------------------------
// Small pieces
// ---------------------------------------------------------------------

/*
avatar() = a round badge with the initials of the user (until profile pictures exist).
The size comes from a fixed list of ready-made classes, because the Content-Security-Policy does not allow
style="width: 40px" in the HTML. The colour depends on the user id, so a person always gets the same colour.
*/
function avatar(array $user, int $size = 40): string
{
    $sizes = [
        36 => 'size-9 text-sm',
        40 => 'size-10 text-base',
        56 => 'size-14 text-xl',
        64 => 'size-16 text-2xl',
    ];
    $colors = ['bg-[#4a6a3c] text-white', 'bg-[#3f5d85] text-white', 'bg-[#8a5a12] text-white', 'bg-[#a14a26] text-white', 'bg-[#6a4f93] text-white'];

    $color   = $colors[$user['UserID'] % count($colors)];
    $letters = mb_strtoupper(mb_substr($user['FirstName'], 0, 1) . mb_substr($user['LastName'], 0, 1));

    return '<span class="inline-flex shrink-0 items-center justify-center rounded-full font-display font-semibold '
        . $color . ' ' . ($sizes[$size] ?? $sizes[40]) . '" aria-hidden="true">' . e($letters) . '</span>';
}
