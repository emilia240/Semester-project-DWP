<?php
/* Admin area (placeholder). Needs: $user (an admin; index.php already refused everybody else). */
?>
<section class="mx-auto max-w-3xl px-4 pb-16 pt-12 sm:px-8">
    <p class="font-hand text-4xl text-accent">Hi Boss</p>
    <h1 class="mt-1 text-5xl font-semibold">Admin area</h1>
    <p class="mt-3 text-lg opacity-80">Hello <?= e($user['Username']) ?>. Only the role <strong>admin</strong> can see this page. The tools (ban, delete, reports) come later.</p>
</section>
