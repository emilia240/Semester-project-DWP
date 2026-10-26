<?php
/*
logout.php  =  end the session.        (FigJam flow: "Log out")

Only accepts POST with a valid CSRF token (the Log out button in the menu is a tiny form).
If it accepted a plain link, any website could log you out just by showing <img src="logout.php">.
*/
require_once 'includes/init.php';

if (!is_post()) {
    redirect_to('index.php');
}

if (!csrf_check()) {
    flash_set('error', 'The form expired. Please try again.');
    redirect_to('index.php');
}

destroy_session();      // empty $_SESSION + delete the cookie + destroy (see session.php)

flash_set('success', 'You are logged out. See you soon!');
redirect_to('login.php');
