<?php
/*
index.php  =  the FRONT CONTROLLER: the one entrance to the site.     (design pattern from the OOP lecture)

Every link on the site looks like   index.php?page=login   and ends up HERE. This file:
  1. loads everything (includes/init.php)
  2. looks the page up in the route table below
  3. checks WHO may open it (the security check lives in ONE place, so no page can forget it)
  4. creates the right controller and calls its method
The controller does the work and shows a view. Unknown page names get a 404.

Route table:   page name  =>  [ controller class,  method,  who may open it ]
Who may open it:
  'all'     everybody, logged in or not
  'guest'   visitors only (a logged-in user is sent to the front page)
  'member'  logged-in members only: e-mail confirmed, not banned (confirm_logged_in() sends the others to the login page)
            and the join steps are finished (otherwise they are sent to the missing step)
  'admin'   role admin only (the join steps must be finished too)
  'onboarding'  like 'member', but without the join-steps check for steps 2 and 3
*/
require_once 'includes/init.php';

$routes = [
    'home'        => ['HomeController', 'index', 'all'],
    'login'       => ['AuthController', 'login', 'guest'],
    'join'        => ['AuthController', 'join', 'guest'],
    'check-inbox' => ['AuthController', 'checkInbox', 'guest'],
    'verify'      => ['AuthController', 'verify', 'all'],
    'resend'      => ['AuthController', 'resend', 'guest'],
    'forgot'      => ['AuthController', 'forgot', 'guest'],
    'reset'       => ['AuthController', 'reset', 'all'],
    'logout'      => ['AuthController', 'logout', 'all'],
    'members'     => ['AccountController', 'index', 'member'],
    'password'    => ['AccountController', 'password', 'member'],
    'join-crafts'     => ['OnboardingController', 'crafts', 'onboarding'],        // join step 2
    'join-guidelines' => ['OnboardingController', 'guidelines', 'onboarding'],    // join step 3
    'admin'       => ['AdminController', 'index', 'admin'],
];

// ?page= is user input. We only use it as a KEY to look up in our own table, never as a file name.
$page = $_GET['page'] ?? 'home';
if (!is_string($page) || !isset($routes[$page])) {
    $page = null;
}

if ($page === null) {
    http_response_code(404);
    $user = logged_in() ? current_user() : null;
    render('pages/not_found', ['title' => 'Page not found', 'user' => $user, 'page' => '404']);
    exit;
}

[$class, $method, $access] = $routes[$page];

if ($access === 'guest' && logged_in()) {
    redirect_to(url('home'));
}
if ($access === 'member' || $access === 'onboarding') {
    $user = confirm_logged_in();
} elseif ($access === 'admin') {
    $user = confirm_admin();
} else {
    $user = logged_in() ? current_user() : null;
}

// Until both join steps are finished, protected pages lead to the missing step.
if ($access === 'member' || $access === 'admin') {
    $missingStep = onboarding_next_step($db, $user);
    if ($missingStep !== null) {
        redirect_to(url($missingStep));
    }
}

$controller = new $class($db, $user, $page);
$controller->$method();
