<?php
/*
The front page. One address, three versions:
  visitor ($user is null)      big "To be Human" + intro + join buttons
  member                       "Hello, <first name>!"
  admin (RoleName = admin)     "Hi Boss" + a button to the admin area
Under the top part: the feed with the tabs "Hot feed", "Boards" and "Following" (members only), and next to it the box "Coolest this week".
Inside the Hot feed a small switch sorts by "Hot" or "Newest". The maker of the week sits next to the greeting.
Needs: $user, $posts (the cards to show), $tab ('hot', 'boards' or 'following'), $sort ('hot' or 'newest'), $coolest (up to 4 cards for "Coolest this week"),
       $maker (the maker of the week, or null: then the card is left out), $makerComments (their post's newest comments, may be empty),
       $pg (the page number), $hasNext (is there a next page?), $atPageLimit,
       and $pins (up to five pictures for the visitor hero).
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

            <?php // The visitor hero shows community pictures, or craft doodles when there are no posts. ?>
            <div class="relative mx-auto w-full max-w-sm lg:max-w-md"<?= $pins ? '' : ' aria-hidden="true"' ?>>
                <div class="absolute inset-0 rotate-[5deg] rounded-sm border border-base-300 bg-base-100 shadow-md"></div>
                <div class="absolute inset-0 -rotate-[4deg] rounded-sm border border-base-300 bg-base-300"></div>
                <?php if ($pins): ?>
                    <?php // The slides share one grid cell, so the tallest caption gives the card enough room. ?>
                    <div class="relative grid rotate-[1.5deg] rounded-sm border border-base-300 bg-base-100 p-3 shadow-xl" data-hero data-interval="1500" role="group" aria-label="Pictures from the community">
                        <?php foreach ($pins as $i => $pin): ?>
                            <figure class="tbh-hero-slide col-start-1 row-start-1 flex flex-col <?= $i === 0 ? 'is-active' : '' ?>" data-slide<?= $i === 0 ? '' : ' aria-hidden="true"' ?>>
                                <div class="relative aspect-[10/11] w-full flex-1 overflow-hidden bg-base-300">
                                    <img src="<?= e(ImageUploader::url($pin['CoverFile'], true)) ?>" alt="" aria-hidden="true" class="absolute inset-0 h-full w-full scale-125 object-cover opacity-70 blur-2xl">
                                    <img src="<?= e(ImageUploader::url($pin['CoverFile'], true)) ?>" alt="<?= e($pin['AltText'] ?? $pin['PostTitle']) ?>" class="relative h-full w-full object-contain">
                                </div>
                                <?php // Long titles and usernames can wrap without being cut off. ?>
                                <figcaption class="flex shrink-0 flex-col justify-end pt-2">
                                    <span class="wrap-anywhere font-display text-lg font-semibold leading-tight"><?= e($pin['PostTitle']) ?></span>
                                    <span class="wrap-anywhere font-hand text-2xl leading-none text-accent">by <?= e($pin['AuthorUsername']) ?></span>
                                </figcaption>
                            </figure>
                        <?php endforeach; ?>
                        <?php if (count($pins) > 1): ?>
                            <?php // Only JavaScript can pause the pictures, so it shows the button after loading. ?>
                            <button type="button" class="btn btn-circle btn-sm absolute right-5 top-5 border-0 bg-base-100/90 text-base-content shadow" data-hero-pause aria-pressed="false" aria-label="Pause the pictures" hidden><span data-hero-pause-icon><?= icon('pause', 16) ?></span><span data-hero-play-icon hidden><?= icon('play', 16) ?></span></button>
                        <?php endif; ?>
                        <span class="absolute -top-4 left-1/2 h-7 w-28 -translate-x-1/2 -rotate-3 bg-[#e6cf9f]/90 shadow-sm" aria-hidden="true"></span>
                    </div>
                <?php else: ?>
                    <div class="relative aspect-[10/13] rotate-[1.5deg] rounded-sm border border-base-300 bg-base-100 p-3 shadow-xl">
                        <div class="relative flex h-full flex-col items-center justify-center gap-6 bg-base-200">
                            <div class="flex items-center gap-6 text-accent"><?= craft_icon('brush', 96) ?><?= craft_icon('scissors', 90) ?></div>
                            <div class="flex items-center gap-6 text-primary"><?= craft_icon('yarn', 100) ?><?= craft_icon('pencil', 90) ?></div>
                            <p class="font-hand text-4xl text-accent">Your work could be next.</p>
                        </div>
                        <span class="absolute -top-4 left-1/2 h-7 w-28 -translate-x-1/2 -rotate-3 bg-[#e6cf9f]/90 shadow-sm"></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php // The intro text always shows. The maker card joins it only when there is a maker this week. ?>
    <section class="mx-auto grid max-w-[120rem] gap-10 px-4 py-14 sm:px-16 md:px-24 lg:px-40 <?= $maker !== null ? 'md:grid-cols-2 md:items-center' : '' ?>" aria-label="<?= $maker !== null ? 'About and maker of the week' : 'About' ?>">
        <div>
            <h2 class="text-4xl font-semibold leading-tight sm:text-5xl">Post what you made. Say how you made it.</h2>
            <p class="mt-5 max-w-xl text-xl opacity-80">Everything here was drawn, stitched, thrown, folded or painted by a person. Beginners and experts sit side by side, and the messy middle is welcome.</p>
        </div>
        <?php if ($maker !== null) { partial('maker_card', ['maker' => $maker, 'comments' => $makerComments]); } ?>
    </section>
<?php else: ?>
    <?php // Members get a compact welcome and more room for the maker card on large screens. ?>
    <section class="mx-auto grid max-w-[120rem] gap-10 px-4 pb-10 pt-12 sm:px-16 md:px-24 lg:px-40 <?= $maker !== null ? ($user['RoleName'] === 'admin' ? 'md:grid-cols-2 md:items-center' : 'lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] lg:items-center') : '' ?>">
        <div class="min-w-0">
        <?php if ($user['RoleName'] === 'admin'): ?>
            <p class="font-hand text-5xl leading-none text-accent sm:text-6xl">Hi Boss</p>
            <h1 class="mt-3 text-6xl font-semibold leading-[0.95] sm:text-7xl lg:text-8xl">Welcome back.</h1>
            <p class="mt-5 text-2xl opacity-80">The reports and the numbers are waiting for you.</p>
            <div class="mt-8"><a href="<?= e(url('admin')) ?>" class="btn btn-primary btn-lg rounded-full">Go to the admin area <?= icon('arrow', 20) ?></a></div>
        <?php else: ?>
            <p class="font-hand text-3xl leading-none text-accent sm:text-4xl">Good to see you</p>
            <h1 class="mt-3 wrap-anywhere text-4xl font-semibold leading-tight sm:text-5xl">Hello, <?= e($user['FirstName']) ?>!</h1>
            <p class="mt-4 text-base opacity-80">Here is what the community made lately. Got something to show?</p>
            <div class="mt-6 flex flex-wrap gap-3"><a href="<?= e(url('create')) ?>" class="btn btn-primary rounded-full">Make a post <?= icon('arrow', 16) ?></a><a href="<?= e(url('profile')) ?>" class="btn btn-ghost rounded-full">My profile</a></div>
        <?php endif; ?>
        </div>
        <?php if ($maker !== null) { partial('maker_card', ['maker' => $maker, 'comments' => $makerComments, 'wide' => $user['RoleName'] !== 'admin']); } ?>
    </section>
<?php endif; ?>

<?php
/* the feed: tabs, then the posts. A few for visitors (with a Join prompt), more for members.
   Next to it (above it on a phone) the box "Coolest this week". With no likes this week the box is left out and the feed gets the full width. */
$hasAside = !empty($coolest);
?>
<section class="mx-auto grid max-w-[120rem] gap-10 px-4 pb-16 sm:px-16 md:px-24 lg:px-40 <?= $hasAside ? 'lg:grid-cols-[minmax(0,1fr)_25rem]' : '' ?>" id="feed" aria-labelledby="feed-h">
    <?php if ($hasAside): ?>
        <aside class="space-y-5 self-start lg:order-2 lg:sticky lg:top-20" aria-label="This week">
            <div class="relative rounded-box border-2 border-dashed border-accent bg-base-200 p-5">
                <span class="absolute -right-2 -top-5 rotate-[14deg] text-accent" aria-hidden="true"><?= craft_icon('star', 40) ?></span>
                <h2 class="font-display text-2xl font-semibold">Coolest this week</h2>
                <p class="font-hand text-2xl leading-tight text-accent">Pinned up just for you!</p>
                <svg class="text-accent" width="120" height="8" viewBox="0 0 120 8" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M2 4c10-5 18 5 29 0s18 5 29 0 18 5 29 0 18 5 29 0"/></svg>
                <?php // A grid keeps each tape with its picture instead of splitting it between columns. ?>
                <div class="mt-5 grid grid-cols-2 items-start gap-x-4">
                    <?php foreach ($coolest as $i => $p) { partial('polaroid_card', ['p' => $p, 'i' => $i]); } ?>
                </div>
            </div>
        </aside>
    <?php endif; ?>

    <div class="min-w-0 lg:order-1">
        <h2 id="feed-h" class="sr-only">The feed</h2>

        <?php
        // The links inside the feed must carry the tab along (and the sort, when it is "newest").
        $feedParams = ['tab' => $tab];
        if ($tab === 'hot' && $sort === 'newest') {
            $feedParams['sort'] = 'newest';
        }
        // The links to the previous and the next page: the same, plus the page number.
        $prevParams       = $feedParams;
        $prevParams['pg'] = $pg - 1;
        $nextParams       = $feedParams;
        $nextParams['pg'] = $pg + 1;

        // The tabs. Visitors get Hot feed and Boards. Members also get Following.
        $tabList = ['hot' => 'Hot feed', 'boards' => 'Boards'];
        if ($user !== null) {
            $tabList['following'] = 'Following';
        }
        ?>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <?php // These tabs are LINKS (each one loads the page again with ?tab=...), so they are a <nav> and not role="tablist". ?>
            <nav class="tabs tabs-border" aria-label="Feed">
                <?php foreach ($tabList as $tabKey => $tabName): ?>
                    <?php
                    // The tab I am on is marked: it gets the tab-active look and aria-current for screen readers. .= adds text to the end.
                    $tabClass   = 'tab text-base font-bold';
                    $tabCurrent = '';
                    if ($tabKey === $tab) {
                        $tabClass  .= ' tab-active';
                        $tabCurrent = ' aria-current="page"';
                    }
                    ?>
                    <a class="<?= e($tabClass) ?>" href="<?= e(url('home', ['tab' => $tabKey]) . '#feed') ?>"<?= $tabCurrent ?>><?= e($tabName) ?></a>
                <?php endforeach; ?>
            </nav>

            <?php // Grid / Single only works with JavaScript, so the buttons start hidden and app.js shows them. The Boards tab has no posts to switch. ?>
            <?php if ($tab !== 'boards'): ?>
                <div class="join hidden" role="group" aria-label="View" data-view-group>
                    <button type="button" class="btn btn-sm join-item btn-neutral" data-view="grid" aria-pressed="true"><?= icon('grid', 16) ?> Grid</button>
                    <button type="button" class="btn btn-sm join-item" data-view="single" aria-pressed="false"><?= icon('single', 16) ?> Single</button>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($tab === 'hot'): ?>
            <?php // A GET form puts the chosen sort in the address, so the feed can be sorted without JavaScript too. ?>
            <div class="mt-4 flex flex-col items-start gap-2">
                <form method="get" action="<?= e(url('home') . '#feed') ?>" class="flex flex-wrap items-center gap-2" data-feed-sort>
                    <?php // GET forms send these fields in the address, so the front controller opens the Hot feed. ?>
                    <input type="hidden" name="page" value="home">
                    <input type="hidden" name="tab" value="hot">
                    <label class="text-sm font-bold opacity-80" for="feed-sort">Sort by</label>
                    <select id="feed-sort" name="sort" class="select select-bordered select-sm">
                    <?php foreach (['hot' => 'Hot', 'newest' => 'Newest'] as $sortKey => $sortName): ?>
                        <?php
                        // The option that matches the current sort is selected when the page opens.
                        $selected = '';
                        if ($sortKey === $sort) {
                            $selected = ' selected';
                        }
                        ?>
                        <option value="<?= e($sortKey) ?>"<?= $selected ?>><?= e($sortName) ?></option>
                    <?php endforeach; ?>
                    </select>
                    <?php // Without JavaScript, the Apply button sends the selected sort. ?>
                    <button type="submit" class="btn btn-sm" data-feed-sort-apply>Apply</button>
                </form>
                <?php // One short sentence says what the order means. ?>
                <?php if ($sort === 'newest'): ?>
                    <span class="text-xs opacity-70">The newest posts first.</span>
                <?php else: ?>
                    <span class="text-xs opacity-70">Most liked and commented lately first.</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($tab === 'boards'): ?>
            <?php // Boards are not built yet: an empty tab with a friendly note. ?>
            <div class="tbh-card mt-8 p-10 text-center">
                <p class="font-display text-2xl font-semibold">Boards are coming soon</p>
                <p class="mx-auto mt-2 max-w-md opacity-80">Soon you can collect the posts you love on boards of your own, and look at the boards other makers have made.</p>
            </div>
        <?php elseif ($posts): ?>
            <?php if ($tab === 'following'): ?>
                <p class="mt-5 font-display text-xl italic opacity-80">Makers you love and cherish in this community.</p>
            <?php endif; ?>
            <div class="tbh-feed mt-6 columns-1 gap-8 md:columns-2 <?= $hasAside ? '2xl:columns-3' : 'lg:columns-3' ?>">
                <?php foreach ($posts as $p) { partial('post_card', ['p' => $p, 'back' => 'home']); } ?>
            </div>
            <?php if ($user === null): ?>
                <?php // Visitors only get a taste of the feed. ?>
                <?php if ($hasNext): ?>
                    <div class="tbh-card mt-4 p-6 text-center sm:p-10">
                        <p class="font-hand text-3xl text-accent">There is so much more</p>
                        <h3 class="mt-1 font-display text-3xl font-semibold">Want to see more?</h3>
                        <p class="mx-auto mt-3 max-w-md opacity-80">Join To Be Human to keep scrolling, share your own work and follow the makers you like.</p>
                        <a href="<?= e(url('join')) ?>" class="btn btn-primary btn-lg mt-6 rounded-full">Sign up, it is free <?= icon('arrow', 20) ?></a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <?php // Members: Previous / Next page, and a friendly end. ?>
                <nav class="mt-6 flex items-center justify-between gap-3" aria-label="Pages of the feed">
                    <?php if ($pg > 1): ?>
                        <a href="<?= e(url('home', $prevParams) . '#feed') ?>" class="btn rounded-full"><?= icon('back', 16) ?> Previous page</a>
                    <?php else: ?><span></span><?php endif; ?>
                    <span class="text-sm opacity-80">Page <?= (int)$pg ?></span>
                    <?php if ($hasNext && !$atPageLimit): ?>
                        <a href="<?= e(url('home', $nextParams) . '#feed') ?>" class="btn btn-primary rounded-full">Next page <?= icon('arrow', 16) ?></a>
                    <?php else: ?><span></span><?php endif; ?>
                </nav>
                <?php if ($atPageLimit): ?>
                    <p class="mt-8 text-center font-display text-xl opacity-80">You have reached the page limit. More posts exist but cannot be shown here.</p>
                <?php elseif (!$hasNext): ?>
                    <p class="mt-8 text-center font-display text-xl opacity-80">You’re all caught up. Go make something!</p>
                <?php endif; ?>
            <?php endif; ?>
        <?php elseif ($pg > 1): ?>
            <p class="mt-6 text-lg opacity-80">There are no more posts on this page.</p>
            <p class="mt-4"><a href="<?= e(url('home', $feedParams) . '#feed') ?>" class="btn rounded-full">Back to the first page</a></p>
        <?php elseif ($tab === 'following'): ?>
            <?php // Nobody followed yet, or the people I follow have not posted. ?>
            <div class="tbh-card mt-8 p-10 text-center">
                <p class="font-display text-2xl font-semibold">Nothing here yet</p>
                <p class="mx-auto mt-2 max-w-md opacity-80">Follow a maker with the Follow button on their profile or next to their name on a post, and their new work shows up here.</p>
                <a href="<?= e(url('home', ['tab' => 'hot']) . '#feed') ?>" class="btn btn-primary mt-5 rounded-full">Go to the Hot feed</a>
            </div>
        <?php else: ?>
            <p class="mt-6 text-lg opacity-80">No posts yet. <?= $user === null ? 'Be the first one, join us!' : 'Be the first one!' ?></p>
            <?php if ($user !== null): ?><p class="mt-4"><a href="<?= e(url('create')) ?>" class="btn btn-primary rounded-full">Make a post</a></p><?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php if ($user === null): ?>
    <?php // About the platform: visitors only. The text is fixed here for now; the admin will be able to edit it later (Must item). ?>
    <section class="border-t border-base-300 bg-base-200" aria-labelledby="about-h">
        <div class="mx-auto grid max-w-[120rem] gap-10 px-4 py-14 sm:px-16 md:px-24 lg:grid-cols-2 lg:gap-16 lg:px-40 lg:py-20">
            <div class="min-w-0">
                <p class="font-hand text-3xl text-accent">About the platform</p>
                <h2 id="about-h" class="mt-2 text-4xl font-semibold leading-tight sm:text-5xl">We built this because a feed of generated images is not a community.</h2>
                <p class="mt-6 max-w-xl text-lg leading-relaxed opacity-90">To Be Human is for people who make things: illustrators, painters, sewists, crocheters, knotters, folders, potters. One rule holds the whole place up: the work you post has to be yours, made by you, without generative tools.</p>
                <p class="mt-4 max-w-xl text-lg leading-relaxed opacity-90">That rule is why beginners post their first crooked hoop here, and why makers who teach technique can be certain their audience is real.</p>
            </div>
            <ul class="min-w-0 space-y-4 self-center">
                <li class="border-l-4 border-accent bg-base-100 p-5">
                    <h3 class="font-display text-xl font-semibold">No generative content</h3>
                    <p class="mt-1 opacity-80">No AI images, generative fill or AI upscaling.</p>
                </li>
                <li class="border-l-4 border-primary bg-base-100 p-5">
                    <h3 class="font-display text-xl font-semibold">Process over polish</h3>
                    <p class="mt-1 opacity-80">Materials, hours and mistakes belong in the caption. That’s the interesting part.</p>
                </li>
                <li class="border-l-4 border-secondary bg-base-100 p-5">
                    <h3 class="font-display text-xl font-semibold">Every discipline, every level</h3>
                    <p class="mt-1 opacity-80">Sewing sits beside digital illustration. Year one sits beside year thirty.</p>
                </li>
            </ul>
        </div>
    </section>
<?php endif; ?>
