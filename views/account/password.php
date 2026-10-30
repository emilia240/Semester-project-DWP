<?php
/* Change password (logged in). Needs: $errors (field name => message). Checked again in PHP (AccountController::password). */
?>
<section class="mx-auto max-w-md px-4 pb-16 pt-12 sm:px-8">
    <h1 class="text-5xl font-semibold">Change password</h1>
    <p class="mt-3 text-lg opacity-80">8-72 characters, at least one letter and one number.</p>

    <?php if (isset($errors['form'])): ?>
        <div class="alert alert-error mt-6" role="alert"><span><?= e($errors['form']) ?></span></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('password')) ?>" class="mt-8 space-y-4" data-validate>
        <?= csrf_field() ?>
        <label class="block">
            <span class="mb-1 block font-bold">Current password</span>
            <input type="password" class="input input-bordered w-full <?= field_class($errors, 'current') ?>" name="current_password" required maxlength="72" autocomplete="current-password"<?= field_attrs($errors, 'current') ?>>
            <?= field_error($errors, 'current') ?>
        </label>
        <label class="block">
            <span class="mb-1 block font-bold">New password</span>
            <span class="input input-bordered flex w-full items-center gap-2 <?= field_class($errors, 'password') ?>">
                <input type="password" class="grow" name="password" required minlength="8" maxlength="72" autocomplete="new-password" data-pw<?= field_attrs($errors, 'password') ?>>
                <button type="button" class="btn btn-ghost btn-xs btn-circle" data-pw-toggle aria-label="Show password"><?= icon('eye', 18) ?></button>
            </span>
            <?= field_error($errors, 'password') ?>
        </label>
        <label class="block">
            <span class="mb-1 block font-bold">Repeat new password</span>
            <input type="password" class="input input-bordered w-full <?= field_class($errors, 'password2') ?>" name="password2" required maxlength="72" autocomplete="new-password"<?= field_attrs($errors, 'password2') ?>>
            <?= field_error($errors, 'password2') ?>
        </label>
        <button type="submit" class="btn btn-primary btn-lg w-full rounded-full">Change password</button>
    </form>
</section>
