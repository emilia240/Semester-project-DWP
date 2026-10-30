<?php
/* Log in. Needs: $error (text or ''), $email (what was typed), $showResend (show the "send me a new link" link). */
partial('auth_open');
?>
<h1 class="text-5xl font-semibold">Welcome back.</h1>
<p class="mt-3 text-lg opacity-80">Pick up where you left off. Your needles are where you left them.</p>

<?php if ($error): ?>
    <div class="alert alert-error mt-6" role="alert">
        <span><?= e($error) ?>
            <?php if ($showResend): ?><a class="link font-bold" href="<?= e(url('resend')) ?>">Send me a new link</a><?php endif; ?>
        </span>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url('login')) ?>" class="mt-8 space-y-4">
    <?= csrf_field() ?>
    <label class="block">
        <span class="mb-1 block font-bold">Email</span>
        <input type="email" class="input input-bordered w-full" name="email" value="<?= e($email) ?>" autocomplete="email" placeholder="you@example.com" required maxlength="190">
    </label>
    <label class="block">
        <span class="mb-1 block font-bold">Password</span>
        <span class="input input-bordered flex w-full items-center gap-2">
            <input type="password" class="grow" name="password" autocomplete="current-password" required maxlength="72" data-pw>
            <button type="button" class="btn btn-ghost btn-xs btn-circle" data-pw-toggle aria-label="Show password"><?= icon('eye', 18) ?></button>
        </span>
    </label>
    <div class="text-right"><a class="link text-sm" href="<?= e(url('forgot')) ?>">Forgot password?</a></div>
    <button type="submit" class="btn btn-primary btn-lg w-full rounded-full">Log in</button>
</form>
<p class="mt-6 text-center">New here? <a class="link font-bold" href="<?= e(url('join')) ?>">Create a new account</a></p>
<?php partial('auth_close'); ?>
