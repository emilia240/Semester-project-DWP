<?php
/* "Forgot password", step 2: choose a new password. Needs: $error (text or ''), $rawToken (the token from the link, kept in the form's address). */
partial('auth_open');
?>
<h1 class="text-5xl font-semibold">Choose a new password</h1>
<p class="mt-3 text-lg opacity-80">At least 8 characters, with a letter and a number.</p>

<?php if ($error): ?>
    <div class="alert alert-error mt-6" role="alert"><span><?= e($error) ?></span></div>
<?php endif; ?>

<form method="post" action="<?= e(url('reset', ['token' => $rawToken])) ?>" class="mt-8 space-y-4" data-validate>
    <?= csrf_field() ?>
    <label class="block">
        <span class="mb-1 block font-bold">New password</span>
        <span class="input input-bordered flex w-full items-center gap-2">
            <input type="password" class="grow" name="password" required minlength="8" maxlength="72" autocomplete="new-password" data-pw>
            <button type="button" class="btn btn-ghost btn-xs btn-circle" data-pw-toggle aria-label="Show password"><?= icon('eye', 18) ?></button>
        </span>
    </label>
    <label class="block">
        <span class="mb-1 block font-bold">Repeat the new password</span>
        <input type="password" class="input input-bordered w-full" name="password2" required maxlength="72" autocomplete="new-password">
    </label>
    <button type="submit" class="btn btn-primary btn-lg w-full rounded-full">Save new password</button>
</form>
<?php partial('auth_close'); ?>
