<?php
/*
The top bar of every normal page. Visitors and members see different buttons.
$user = the logged-in user (array) or null.   $page = the name of the current page.
More links (Explore, Create post, notifications, search) are added here when those pages exist.
*/
?>
<header class="sticky top-0 z-40 border-b border-base-300 bg-base-100/90 backdrop-blur">
    <div class="navbar mx-auto max-w-[120rem] gap-2 px-4 sm:px-16 md:px-24 lg:px-40">
        <div class="navbar-start w-auto">
            <a href="<?= e(url('home')) ?>" class="font-display text-xl font-bold tracking-tight sm:text-2xl" aria-label="To Be Human, home">To Be Human</a>
        </div>

        <div class="navbar-end ml-auto w-auto flex-1 gap-1 sm:gap-2">
            <?php if ($user === null): ?>
                <a href="<?= e(url('login')) ?>" class="btn btn-ghost">Log in</a>
                <a href="<?= e(url('join')) ?>" class="btn btn-primary rounded-full">Join us</a>
            <?php else: ?>
                <div class="dropdown dropdown-end">
                    <button type="button" tabindex="0" class="btn btn-ghost btn-circle" aria-label="Your menu"><?= avatar($user, 40) ?></button>
                    <ul tabindex="0" class="menu dropdown-content z-50 mt-3 w-64 rounded-box border border-base-300 bg-base-100 p-2 shadow-xl">
                        <li class="menu-title normal-case"><?= e($user['FirstName'] . ' ' . $user['LastName']) ?><br><span class="font-normal opacity-80">@<?= e($user['Username']) ?></span></li>
                        <li><a href="<?= e(url('members')) ?>"><?= icon('user', 18) ?> My account</a></li>
                        <li><a href="<?= e(url('password')) ?>"><?= icon('lock', 18) ?> Change password</a></li>
                        <?php if ($user['RoleName'] === 'admin'): ?>
                            <li><a href="<?= e(url('admin')) ?>"><?= icon('shield', 18) ?> Admin area</a></li>
                        <?php endif; ?>
                        <li><button type="button" data-theme-toggle><?= icon('moon', 18) ?> Dark / light</button></li>
                        <li>
                            <!-- Log out is a tiny POST form with a CSRF token: another website cannot log you out with a link or an image. -->
                            <form method="post" action="<?= e(url('logout')) ?>" class="p-0">
                                <?= csrf_field() ?>
                                <button type="submit" class="flex w-full items-center gap-2 px-3 py-2 text-left"><?= icon('logout', 18) ?> Log out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>
<main id="main" class="relative isolate pb-24 md:pb-0">
<?php partial('decor'); ?>
<div class="mx-auto max-w-[120rem] px-4 pt-6 sm:px-16 md:px-24 lg:px-40 empty:hidden"><?php partial('flash'); ?></div>
