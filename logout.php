<?php
/*
logout.php  =  end the session.        (FigJam flow: "Log out")
destroy_session() (see session.php) empties $_SESSION, deletes the cookie and destroys the session.
(In a later commit this becomes POST-only with a CSRF token.)
*/
require_once 'includes/init.php';

destroy_session();

flash_set('success', 'You are logged out. See you soon!');
redirect_to('login.php');
