<?php
/* "Send me a new link" (for a confirmation link that is lost or too old). Needs: $email (what was typed). */
partial('auth_open');
?>
<h1 class="text-5xl font-semibold">Send me a new link</h1>
<p class="mt-3 text-lg opacity-80">Type the e-mail you signed up with and we will send a fresh confirmation link.</p>

<form method="post" action="<?= e(url('resend')) ?>" class="mt-8 space-y-4">
    <?= csrf_field() ?>
    <label class="block">
        <span class="mb-1 block font-bold">Email address</span>
        <input type="email" class="input input-bordered w-full" name="email" value="<?= e($email) ?>" placeholder="you@example.com" required maxlength="190" autocomplete="email">
    </label>
    <button type="submit" class="btn btn-primary btn-lg w-full rounded-full">Send me a new link</button>
</form>
<p class="mt-4 text-sm opacity-80">For your safety we say the same thing whether or not the e-mail has an account.</p>
<p class="mt-6 text-center"><a class="link" href="<?= e(url('login')) ?>">Back to log in</a></p>
<?php partial('auth_close'); ?>
