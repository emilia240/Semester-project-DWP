<?php
/* The page after sign-up. The text says "a link" on purpose: it is the same message whatever happened to the e-mail. */
partial('auth_open');
?>
<div class="grid h-20 w-20 place-items-center rounded-full bg-secondary/20 text-4xl" aria-hidden="true">✉️</div>
<h1 class="mt-6 text-5xl font-semibold">Check your inbox</h1>
<p class="mt-3 text-lg opacity-80">We sent you a link. Open it to confirm your e-mail, then you can log in. The link works for <?= (int)VERIFY_TOKEN_HOURS ?> hours and only once.</p>

<div class="mt-8 space-y-3">
    <a href="<?= e(url('resend')) ?>" class="btn w-full rounded-full">Send me a new link</a>
    <p class="text-center text-sm opacity-80">Nothing there? Check your spam folder, or ask for a new link (once a minute).</p>
</div>
<p class="mt-8 text-center"><a class="link" href="<?= e(url('join')) ?>">Wrong e-mail? Start again</a></p>
<?php partial('auth_close'); ?>
