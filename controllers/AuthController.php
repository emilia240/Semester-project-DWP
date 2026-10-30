<?php
/*
AuthController  =  everything about getting IN and OUT of the site:
log in, log out, sign up, confirm the e-mail, forgot and reset password.
Each public method is one page (see the route table in index.php). The same method answers both:
  GET   the visitor opens the page      -> show the form
  POST  the visitor sent the form       -> check it, do the work, redirect (or show the form again with errors)
*/
class AuthController extends BaseController
{
    // =================================================================
    // LOG IN                                              (FigJam flow: "Log in")
    //
    //  1. CSRF ok? Not locked out by too many failures?
    //  2. Find the user by e-mail
    //  3. password_verify(typed password, hash from the database)
    //  4. Correct, but e-mail not confirmed -> "Please confirm your email"
    //  5. Correct, but banned              -> "Account suspended"
    //  6. Otherwise: start the session
    //
    // We never say WHICH part was wrong ("unknown e-mail" vs "wrong password"). Both give the
    // same sentence, so an attacker cannot use the form to find out who is registered.
    // =================================================================
    public function login(): void
    {
        $users      = new UserModel($this->db);
        $error      = '';
        $email      = '';
        $showResend = false;

        if (is_post()) {

            if (!csrf_check()) {
                $error = 'The form expired. Please try again.';

            } elseif (login_is_locked()) {
                $error = 'Too many failed attempts. Please wait a minute and try again.';

            } else {
                $email    = mb_strtolower(post_str('email'));
                $password = post_raw('password');

                // ---- 2. find the user (only the columns we need) ----
                $user = $users->findForLogin($email);          // an array, or null if there is no such e-mail

                // ---- 3. check the password ----
                // If the e-mail does not exist we still verify against a fake hash, so both cases take equally long.
                $hashToCheck = $user ? $user['PasswordHash'] : DUMMY_HASH;
                $passwordOk  = password_verify($password, $hashToCheck);

                if (!$user || !$passwordOk) {
                    login_register_failure();
                    $error = 'Wrong e-mail or password.';

                } elseif ($user['EmailVerifiedAt'] === null) {
                    // ---- 4. the flowchart's "Email verified?" diamond. Safe to tell: they proved they know the password.
                    $error      = 'Please confirm your e-mail first. We sent you a link when you signed up.';
                    $showResend = true;

                } elseif (is_banned($user)) {
                    // ---- 5. banned ----
                    $error = $user['BannedUntil'] === null
                        ? 'This account has been suspended.'
                        : 'This account is suspended until ' . date('j M Y H:i', strtotime($user['BannedUntil'])) . '.';

                } else {
                    // ---- 6. success ----
                    login_clear_failures();

                    // Bcrypt can get "stronger" over the years. If our settings changed since this hash was made, re-hash now.
                    if (password_needs_rehash($user['PasswordHash'], PASSWORD_BCRYPT, ['cost' => 10])) {
                        $users->updatePasswordHash((int)$user['UserID'], password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]));
                    }

                    // IMPORTANT: new session id at the moment of login. Otherwise an attacker who planted a known
                    // session id in the victim's browser ("session fixation") would be logged in as the victim.
                    session_regenerate_id(true);
                    unset($_SESSION['csrf_token']);                 // fresh CSRF token for the new session
                    $_SESSION['user_id'] = (int)$user['UserID'];    // THIS line is "being logged in"

                    flash_set('success', 'Welcome back, ' . $user['Username'] . '!');
                    redirect_to(url('home'));
                }
            }
        }

        $this->render('auth/login', [
            'layout'     => 'split',
            'title'      => 'Log in',
            'error'      => $error,
            'email'      => $email,
            'showResend' => $showResend,
        ]);
    }
}
