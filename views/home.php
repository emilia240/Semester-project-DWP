<?php
/*
The front page. One address, three versions:
  visitor ($user is null)      big "To be Human" + intro + join buttons
  member                       "Hello, <first name>!"
  admin (RoleName = admin)     "Hi Boss" + a button to the admin area
Under the top part: the newest posts. "Coolest this week" and the maker of the week come later.
*/
?>
<?php if ($user === null): ?>
    <section class="relative isolate overflow-hidden" aria-label="Welcome">
        <div class="pointer-events-none absolute -right-24 -top-24 -z-10 hidden h-[34rem] w-[34rem] rounded-full bg-accent/15 lg:block" aria-hidden="true"></div>

        <div class="mx-auto grid max-w-[120rem] items-center gap-12 px-4 pb-14 pt-10 sm:px-16 md:px-24 lg:grid-cols-[3fr_2fr] lg:gap-16 lg:px-40 lg:py-16">
            <div class="min-w-0">
                <p class="font-hand text-3xl text-accent sm:text-5xl">Real hands. Real tools. Real work.</p>
                <div class="tbh-wordmark-box mt-2"><h1 class="tbh-wordmark" aria-label="To be human">To <em>be</em> Human</h1></div>
                <svg viewBox="0 0 300 14" class="mt-3 w-2/3 text-accent" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" aria-hidden="true"><path d="M3 9 C 30 2, 58 13, 88 7 S 148 2, 178 8 S 248 13, 297 4"/></svg>
                <p class="mt-6 max-w-2xl text-xl leading-relaxed sm:text-2xl">A calm place to share what you make with your hands, and to see what others made.</p>
                <p class="mt-3 font-hand text-3xl text-accent">No ads. No AI images. Just people making things.</p>
                <div class="mt-8 flex flex-wrap items-center gap-4">
                    <a href="<?= e(url('join')) ?>" class="btn btn-primary btn-lg rounded-full">Join the platform <?= icon('arrow', 20) ?></a>
                    <a href="<?= e(url('login')) ?>" class="btn btn-ghost btn-lg rounded-full">I already have an account</a>
                </div>
            </div>

            <!-- A card "pinned up" with paper behind it and a piece of tape. Pictures from the community go here once posts exist. -->
            <div class="relative mx-auto w-full max-w-sm lg:max-w-md" aria-hidden="true">
                <div class="absolute inset-0 rotate-[5deg] rounded-sm border border-base-300 bg-base-100 shadow-md"></div>
                <div class="absolute inset-0 -rotate-[4deg] rounded-sm border border-base-300 bg-base-300"></div>
                <div class="relative aspect-[10/13] rotate-[1.5deg] rounded-sm border border-base-300 bg-base-100 p-3 shadow-xl">
                    <div class="relative flex h-full flex-col items-center justify-center gap-6 bg-base-200">
                        <div class="flex items-center gap-6 text-accent"><?= craft_icon('brush', 96) ?><?= craft_icon('scissors', 90) ?></div>
                        <div class="flex items-center gap-6 text-primary"><?= craft_icon('yarn', 100) ?><?= craft_icon('pencil', 90) ?></div>
                        <p class="font-hand text-4xl text-accent">Your work could be next.</p>
                    </div>
                    <span class="absolute -top-4 left-1/2 h-7 w-28 -translate-x-1/2 -rotate-3 bg-[#e6cf9f]/90 shadow-sm"></span>
                </div>
            </div>
        </div>
    </section>
<?php else: ?>
    <section class="mx-auto max-w-[120rem] px-4 pb-16 pt-12 sm:px-16 md:px-24 lg:px-40">
        <?php if ($user['RoleName'] === 'admin'): ?>
            <p class="font-hand text-5xl leading-none text-accent sm:text-6xl">Hi Boss</p>
            <h1 class="mt-3 text-6xl font-semibold leading-[0.95] sm:text-7xl lg:text-8xl">Welcome back.</h1>
            <p class="mt-5 text-2xl opacity-80">The reports and the numbers are waiting for you.</p>
            <div class="mt-8"><a href="<?= e(url('admin')) ?>" class="btn btn-primary btn-lg rounded-full">Go to the admin area <?= icon('arrow', 20) ?></a></div>
        <?php else: ?>
            <p class="font-hand text-5xl leading-none text-accent sm:text-6xl">Good to see you</p>
            <h1 class="mt-3 text-6xl font-semibold leading-[0.95] sm:text-7xl lg:text-8xl">Hello, <?= e($user['FirstName']) ?>!</h1>
            <p class="mt-5 text-2xl opacity-80">Here is what the community made lately. Got something to show?</p>
            <div class="mt-8 flex flex-wrap gap-3"><a href="<?= e(url('create')) ?>" class="btn btn-primary btn-lg rounded-full">Make a post <?= icon('arrow', 20) ?></a><a href="<?= e(url('members')) ?>" class="btn btn-ghost btn-lg rounded-full">My account</a></div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php /* the newest posts: a few for visitors (with a Join prompt), more for members */ ?>
<section class="mx-auto max-w-[120rem] px-4 pb-16 sm:px-16 md:px-24 lg:px-40" aria-labelledby="fresh-h">
    <h2 id="fresh-h" class="font-display text-3xl font-semibold"><?= $user === null ? 'Fresh from the community' : 'Newest posts' ?></h2>
    <?php if ($posts): ?>
        <div class="mt-6 columns-1 gap-8 sm:columns-2 lg:columns-3">
            <?php foreach ($posts as $p) { partial('post_card', ['p' => $p, 'back' => 'home']); } ?>
        </div>
        <?php if ($user === null): ?>
            <div class="tbh-card mt-4 p-6 text-center">
                <p class="font-bold">There is more to see inside.</p>
                <p class="mt-1 opacity-80">Join to share your own work and follow the makers you like.</p>
                <a href="<?= e(url('join')) ?>" class="btn btn-primary mt-4 rounded-full">Join us</a>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <p class="mt-4 text-lg opacity-80">No posts yet. <?= $user === null ? 'Be the first one, join us!' : 'Be the first one!' ?></p>
        <?php if ($user !== null): ?><p class="mt-4"><a href="<?= e(url('create')) ?>" class="btn btn-primary rounded-full">Make a post</a></p><?php endif; ?>
    <?php endif; ?>
</section>
