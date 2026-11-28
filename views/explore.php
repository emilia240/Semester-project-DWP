<?php
/*
The Explore page: a wall of posts to browse. Step 1 = the newest posts of everybody, with pages.
Needs: $user (null = a visitor), $posts (the cards to show), $pg (the page number), $hasNext (is there a next page?), $atPageLimit.
*/
?>
<section class="mx-auto max-w-[120rem] px-4 pb-16 pt-6 sm:px-16 md:px-24 lg:px-40" aria-labelledby="explore-h">
    <p class="font-hand text-3xl text-accent">Look around</p>
    <h1 id="explore-h" class="mt-1 text-4xl font-semibold sm:text-5xl">Explore</h1>
    <p class="mt-3 max-w-2xl text-lg opacity-80">Everything makers shared lately, newest first.</p>

    <?php if ($posts): ?>
        <?php // Show each cover photo as a link to the full post, without the other card details. ?>
        <div class="mt-8 grid grid-cols-2 gap-3 md:grid-cols-3">
            <?php foreach ($posts as $p): ?>
                <a href="<?= e(url('post', ['id' => $p['PostID']])) ?>" class="block bg-base-300" aria-label="Open <?= e($p['PostTitle']) ?>">
                    <?php // Use the smaller image for a faster grid and keep the whole picture visible. ?>
                    <img src="<?= e(ImageUploader::url($p['CoverFile'], true)) ?>" <?= image_attrs($p['CoverFile'], true) ?> alt="<?= e($p['AltText'] ?? $p['PostTitle']) ?>" loading="lazy" class="aspect-square h-auto w-full object-contain">
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($user === null): ?>
            <?php // Visitors only get a taste of the wall. ?>
            <?php if ($hasNext): ?>
                <div class="tbh-card mt-4 p-6 text-center sm:p-10">
                    <p class="font-hand text-3xl text-accent">There is so much more</p>
                    <h2 class="mt-1 font-display text-3xl font-semibold">Want to see more?</h2>
                    <p class="mx-auto mt-3 max-w-md opacity-80">Join To Be Human to keep scrolling, share your own work and follow the makers you like.</p>
                    <a href="<?= e(url('join')) ?>" class="btn btn-primary btn-lg mt-6 rounded-full">Sign up, it is free <?= icon('arrow', 20) ?></a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <?php // Members: Previous / Next page, and a friendly end. ?>
            <nav class="mt-6 flex items-center justify-between gap-3" aria-label="Pages of the wall">
                <?php if ($pg > 1): ?>
                    <a href="<?= e(url('explore', ['pg' => $pg - 1])) ?>" class="btn rounded-full"><?= icon('back', 16) ?> Previous page</a>
                <?php else: ?><span></span><?php endif; ?>
                <span class="text-sm opacity-80">Page <?= (int)$pg ?></span>
                <?php if ($hasNext && !$atPageLimit): ?>
                    <a href="<?= e(url('explore', ['pg' => $pg + 1])) ?>" class="btn btn-primary rounded-full">Next page <?= icon('arrow', 16) ?></a>
                <?php else: ?><span></span><?php endif; ?>
            </nav>
            <?php if ($atPageLimit): ?>
                <p class="mt-8 text-center font-display text-xl opacity-80">You have reached the page limit. More posts exist but cannot be shown here.</p>
            <?php elseif (!$hasNext): ?>
                <p class="mt-8 text-center font-display text-xl opacity-80">You’re all caught up. Go make something!</p>
            <?php endif; ?>
        <?php endif; ?>
    <?php elseif ($pg > 1): ?>
        <?php // A page number that is too high: nothing on this page. ?>
        <p class="mt-8 text-lg opacity-80">There are no more posts on this page.</p>
        <p class="mt-4"><a href="<?= e(url('explore')) ?>" class="btn rounded-full">Back to the first page</a></p>
    <?php else: ?>
        <p class="mt-8 text-lg opacity-80">No posts yet. <?= $user === null ? 'Be the first one, join us!' : 'Be the first one!' ?></p>
        <?php if ($user !== null): ?><p class="mt-4"><a href="<?= e(url('create')) ?>" class="btn btn-primary rounded-full">Make a post</a></p><?php endif; ?>
    <?php endif; ?>
</section>
