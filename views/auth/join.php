<?php
/*
Join us (sign up). Needs: $errors (field name => message), $old (what was typed before; never the passwords).
Every rule is checked again in PHP (AuthController::join), the HTML attributes are only a convenience.
*/
partial('auth_open');
?>
<p class="font-hand text-3xl text-accent">A place for you</p>
<h1 class="mt-1 text-5xl font-semibold">Come on in.</h1>
<p class="mt-3 text-lg opacity-80">You don’t have to be an expert.<br>You just have to be you.</p>

<?php if (isset($errors['form'])): ?>
    <div class="alert alert-error mt-6" role="alert"><span><?= e($errors['form']) ?></span></div>
<?php endif; ?>

<form method="post" action="<?= e(url('join')) ?>" class="mt-8 space-y-4" data-validate>
    <?= csrf_field() ?>

    <div class="grid gap-4 sm:grid-cols-2">
        <label class="block">
            <span class="mb-1 block font-bold">First name</span>
            <input class="input input-bordered w-full <?= field_class($errors, 'first_name') ?>" name="first_name" value="<?= e($old['first_name']) ?>" autocomplete="given-name" placeholder="Alex" required maxlength="110"<?= field_attrs($errors, 'first_name') ?>>
            <?= field_error($errors, 'first_name') ?>
        </label>
        <label class="block">
            <span class="mb-1 block font-bold">Last name</span>
            <input class="input input-bordered w-full <?= field_class($errors, 'last_name') ?>" name="last_name" value="<?= e($old['last_name']) ?>" autocomplete="family-name" placeholder="Reed" required maxlength="110"<?= field_attrs($errors, 'last_name') ?>>
            <?= field_error($errors, 'last_name') ?>
        </label>
    </div>

    <label class="block">
        <span class="mb-1 block font-bold">Username</span>
        <input class="input input-bordered w-full <?= field_class($errors, 'username') ?>" name="username" value="<?= e($old['username']) ?>" autocomplete="username" placeholder="alexmakes" required minlength="3" maxlength="50"<?= field_attrs($errors, 'username') ?>>
        <span class="mt-1 block text-sm opacity-80">This is how others see you. Letters, numbers and underscores.</span>
        <?= field_error($errors, 'username') ?>
    </label>

    <label class="block">
        <span class="mb-1 block font-bold">Email address</span>
        <input type="email" class="input input-bordered w-full <?= field_class($errors, 'email') ?>" name="email" value="<?= e($old['email']) ?>" autocomplete="email" placeholder="you@example.com" required maxlength="190"<?= field_attrs($errors, 'email') ?>>
        <?= field_error($errors, 'email') ?>
    </label>

    <label class="block">
        <span class="mb-1 block font-bold">Birthdate</span>
        <input type="date" class="input input-bordered w-full <?= field_class($errors, 'birthdate') ?>" name="birthdate" value="<?= e($old['birthdate']) ?>" autocomplete="bday" required<?= field_attrs($errors, 'birthdate') ?>>
        <span class="mt-1 block text-sm opacity-80">You need to be at least <?= (int)MIN_AGE ?>. We never show it.</span>
        <?= field_error($errors, 'birthdate') ?>
    </label>

    <label class="block">
        <span class="mb-1 block font-bold">Password</span>
        <span class="input input-bordered flex w-full items-center gap-2 <?= field_class($errors, 'password') ?>">
            <input type="password" class="grow" name="password" autocomplete="new-password" placeholder="At least 8 characters, a letter and a number" required minlength="8" maxlength="72" data-pw<?= field_attrs($errors, 'password') ?>>
            <button type="button" class="btn btn-ghost btn-xs btn-circle" data-pw-toggle aria-label="Show password"><?= icon('eye', 18) ?></button>
        </span>
        <?= field_error($errors, 'password') ?>
    </label>

    <label class="block">
        <span class="mb-1 block font-bold">Repeat password</span>
        <input type="password" class="input input-bordered w-full <?= field_class($errors, 'password2') ?>" name="password2" autocomplete="new-password" required maxlength="72"<?= field_attrs($errors, 'password2') ?>>
        <?= field_error($errors, 'password2') ?>
    </label>

    <div>
        <label class="flex cursor-pointer items-start gap-3 rounded-box border border-accent/40 bg-accent/10 p-4">
            <input type="checkbox" class="checkbox checkbox-primary mt-0.5" name="human_made" value="1" <?= $old['human_made'] ? 'checked' : '' ?><?= field_attrs($errors, 'human_made') ?>>
            <span>I only share work I made myself, by hand. No AI-generated pictures.</span>
        </label>
        <?= field_error($errors, 'human_made') ?>
    </div>

    <?php // Cloudflare adds a one-use response to the form after its human check. ?>
    <div>
        <div class="cf-turnstile" data-sitekey="<?= e(TURNSTILE_SITE_KEY) ?>"></div>
        <?= field_error($errors, 'captcha') ?>
    </div>

    <button type="submit" class="btn btn-primary btn-lg w-full rounded-full">Create account <?= icon('arrow', 20) ?></button>
</form>
<?php // This external script displays the Turnstile widget above. ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<p class="mt-6 text-center">Already a member? <a class="link font-bold" href="<?= e(url('login')) ?>">Log in</a></p>
<?php partial('auth_close'); ?>
