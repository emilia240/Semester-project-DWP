<?php
/* My account. Needs: $user (the logged-in member, from index.php). Everything from the database goes through e(). */
?>
<section class="mx-auto max-w-3xl px-4 pb-16 pt-12 sm:px-8">
    <p class="font-hand text-4xl text-accent">Your corner</p>
    <h1 class="mt-1 text-5xl font-semibold">My account</h1>
    <p class="mt-3 text-lg opacity-80">Hello <?= e($user['FirstName'] . ' ' . $user['LastName']) ?>. You are in because you are logged in, your e-mail is confirmed and you are not banned.</p>

    <div class="tbh-card mt-8 overflow-x-auto">
        <table class="table">
            <tbody>
                <tr><th scope="row">Username</th><td><?= e($user['Username']) ?></td></tr>
                <tr><th scope="row">E-mail</th><td><?= e($user['Email']) ?></td></tr>
                <tr><th scope="row">Role</th><td><?= e($user['RoleName']) ?></td></tr>
                <tr><th scope="row">E-mail confirmed</th><td><?= e($user['EmailVerifiedAt']) ?></td></tr>
            </tbody>
        </table>
    </div>

    <p class="mt-6"><a class="btn btn-outline rounded-full" href="<?= e(url('password')) ?>"><?= icon('lock', 18) ?> Change my password</a></p>
</section>
