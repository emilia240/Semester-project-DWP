<?php
/* "Forgot password", step 1. Needs: $email (what was typed). */
partial('auth_open');
?>
<h1 class="text-5xl font-semibold">Forgot your password?</h1>
<p class="mt-3 text-lg opacity-80">It happens to the best of us. Tell us your e-mail and we will send you a link. It works for <?= (int)RESET_TOKEN_HOURS ?> hour and only once.</p>

<form method="post" action="<?= e(url('forgot')) ?>" class="mt-8 space-y-4">
    <?= csrf_field() ?>
    <label class="block">
        <span class="mb-1 block font-bold">Email</span>
        <input type="email" class="input input-bordered w-full" name="email" value="<?= e($email) ?>" placeholder="you@example.com" required maxlength="190" autocomplete="email">
    </label>
    <button type="submit" class="btn btn-primary btn-lg w-full rounded-full">Send me a link</button>
</form>
<p class="mt-4 text-sm opacity-80">For your safety we say the same thing whether or not the e-mail has an account.</p>
<p class="mt-6 text-center"><a class="link" href="<?= e(url('login')) ?>">Back to log in</a></p>
<?php partial('auth_close'); ?>
