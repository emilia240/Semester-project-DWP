<?php
/* The bottom of every normal page: footer, mobile bar and the cookie notice. */
?>
</main>

<footer class="border-t border-base-300 bg-base-100 pb-28 md:pb-10">
    <div class="mx-auto grid max-w-[120rem] gap-10 px-4 py-12 sm:grid-cols-2 sm:px-16 md:px-24 lg:px-40">
        <div>
            <p class="font-display text-2xl font-bold">To Be Human</p>
            <p class="mt-2 max-w-sm opacity-80">A place for work made by hands. No generated images, no synthetic feeds, just makers showing what they made and how they made it.</p>
            <p class="mt-4 font-hand text-2xl text-accent">Made slowly, by hand.</p>
        </div>
        <nav aria-label="Account">
            <h2 class="font-sans text-xs font-bold uppercase tracking-widest opacity-80"><?= $user === null ? 'Come in' : 'You' ?></h2>
            <ul class="mt-3 space-y-2">
                <li><a class="link link-hover" href="<?= e(url('home')) ?>">Home</a></li>
                <?php if ($user === null): ?>
                    <li><a class="link link-hover" href="<?= e(url('join')) ?>">Join us</a></li>
                    <li><a class="link link-hover" href="<?= e(url('login')) ?>">Log in</a></li>
                <?php else: ?>
                    <li><a class="link link-hover" href="<?= e(url('members')) ?>">My account</a></li>
                    <li><a class="link link-hover" href="<?= e(url('password')) ?>">Change password</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <p class="border-t border-base-300 py-4 text-center text-sm opacity-80">© 2026 To Be Human · No ads. No AI images.</p>
</footer>

<!-- bottom bar on a phone (daisyUI "dock") -->
<nav class="dock dock-md z-40 border-t border-base-300 bg-base-100 md:hidden" aria-label="Main">
    <a href="<?= e(url('home')) ?>" class="<?= $page === 'home' ? 'dock-active' : '' ?>"><?= icon('home', 22) ?><span class="dock-label">Home</span></a>
    <?php if ($user === null): ?>
        <a href="<?= e(url('login')) ?>" class="<?= $page === 'login' ? 'dock-active' : '' ?>"><?= icon('user', 22) ?><span class="dock-label">Log in</span></a>
        <a href="<?= e(url('join')) ?>" class="<?= $page === 'join' ? 'dock-active' : '' ?>"><?= icon('plus', 22) ?><span class="dock-label">Join</span></a>
    <?php else: ?>
        <a href="<?= e(url('members')) ?>" class="<?= $page === 'members' ? 'dock-active' : '' ?>"><?= icon('user', 22) ?><span class="dock-label">Account</span></a>
        <a href="<?= e(url('password')) ?>" class="<?= $page === 'password' ? 'dock-active' : '' ?>"><?= icon('lock', 22) ?><span class="dock-label">Password</span></a>
    <?php endif; ?>
</nav>

<!-- cookie notice (assets/js/app.js shows it once). We only use the session cookie and the theme choice. -->
<div id="cookie-note" class="fixed bottom-36 left-3 right-3 z-50 hidden max-w-sm rounded-box border border-base-300 bg-base-100 p-4 shadow-xl md:bottom-16 md:left-4 md:right-auto" role="dialog" aria-label="Cookies">
    <p class="text-sm">We only use cookies that keep you logged in and remember your theme.</p>
    <button type="button" class="btn btn-primary btn-sm mt-3 rounded-full" data-cookie-ok>Got it</button>
</div>
