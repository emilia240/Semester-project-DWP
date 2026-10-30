<?php
/*
AccountController  =  pages for a logged-in member (index.php already checked that, see the 'member' routes).

  members   "My account": the details we have about you
  password  change the password while logged in      (FigJam flow: "Change password (logged in)")
*/
class AccountController extends BaseController
{
    public function index(): void
    {
        $this->render('account/index', ['title' => 'My account']);
    }

    // =================================================================
    //   Settings -> Password: current password, new password, repeat new password
    //   current password correct?                   no  -> "Current password is wrong"
    //   new passwords match and are strong enough?  no  -> show the errors
    //   yes -> save the new hash
    //
    // The current password is asked on purpose: it stops someone who sits down at an unlocked computer
    // from changing the password. No e-mail link is involved here (that is "forgot password").
    // =================================================================
    public function password(): void
    {
        $users  = new UserModel($this->db);
        $tokens = new TokenModel($this->db);
        $errors = [];

        if (is_post()) {
            $current   = post_raw('current_password');
            $password  = post_raw('password');
            $password2 = post_raw('password2');

            if (!csrf_check()) {
                $errors['form'] = 'The form expired. Please try again.';

            } elseif (login_is_locked()) {
                // Someone at an unlocked computer could try to GUESS the current password, so wrong guesses count like at login.
                $errors['form'] = 'Too many failed attempts. Please wait a minute and try again.';

            } else {
                $hash = $users->findHash((int)$this->user['UserID']);

                if ($hash === null || !password_verify($current, $hash)) {
                    login_register_failure();
                    $errors['current'] = 'Current password is wrong.';

                } elseif ($msg = validate_password($password, $this->user['Username'], $this->user['Email'])) {
                    $errors['password'] = $msg;

                } elseif ($password !== $password2) {
                    $errors['password2'] = 'The two new passwords are not the same.';

                } elseif ($password === $current) {
                    $errors['password'] = 'The new password must be different from the current one.';

                } else {
                    // ---- save ----
                    $this->db->beginTransaction();
                    $users->updatePasswordHash((int)$this->user['UserID'], password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]));
                    // A reset link that is still out there must not work any more.
                    $tokens->deleteForUser((int)$this->user['UserID'], ['reset_password']);
                    $this->db->commit();

                    // Sensitive change = new session id (same idea as at login).
                    session_regenerate_id(true);
                    unset($_SESSION['csrf_token']);
                    login_clear_failures();

                    send_password_changed_email($this->user['Email'], $this->user['Username']);
                    flash_set('success', 'Password changed.');
                    redirect_to(url('password'));
                }
            }
        }

        $this->render('account/password', ['title' => 'Change password', 'errors' => $errors]);
    }
}
