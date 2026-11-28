<?php
/*
The Explore page: a wall of cover photos to browse. Step 1 = the newest posts of everybody, with pages.
Needs: $user (null = a visitor), $posts (the cards to show), $pg (the page number), $hasNext (is there a next page?), $atPageLimit.
*/
?>
<section class="mx-auto max-w-[120rem] px-4 pb-16 pt-6 sm:px-16 md:px-24 lg:px-40" aria-labelledby="explore-h">
    <?php // Keep the page heading for screen readers, without showing it above the photos. ?>
    <h1 id="explore-h" class="sr-only">Explore</h1>

    <?php if ($posts): ?>
        <?php // The wall: each cover photo is a tile. A tile keeps the real shape of its photo (tall, wide or square), so the wall looks uneven, like a stone wall. ?>
        <?php // columns-2 / columns-3 / columns-4 = the number of columns on a phone / a medium screen / a big screen. ?>
        <div class="mt-8 columns-2 gap-3 md:columns-3 lg:columns-4">
            <?php foreach ($posts as $p): ?>
                <?php
                // The maker, in the shape that avatar() wants. (The card query calls these columns Author...)
                $author = ['UserID' => $p['UserID'], 'FirstName' => $p['AuthorFirstName'], 'LastName' => $p['AuthorLastName'], 'AvatarFile' => $p['AuthorAvatarFile']];
                // A screen reader reads this text for the link, so it hears the title and the maker even when the overlay is hidden.
                $tileLabel = 'Open ' . $p['PostTitle'] . ' by ' . $p['AuthorUsername'];
                ?>
                <div class="mb-3 break-inside-avoid">
                    <a href="<?= e(url('post', ['id' => $p['PostID']])) ?>" class="group relative block overflow-hidden rounded-box bg-base-300" aria-label="<?= e($tileLabel) ?>">
                        <?php // The smaller picture loads faster. h-auto + w-full keeps the whole picture visible in its own shape. ?>
                        <img src="<?= e(ImageUploader::url($p['CoverFile'], true)) ?>" <?= image_attrs($p['CoverFile'], true) ?> alt="<?= e($p['AltText'] ?? $p['PostTitle']) ?>" loading="lazy" class="h-auto w-full">
                        <?php // The info layer: invisible until the mouse is on the tile or the keyboard focus is on the link. ?>
                        <?php // pointer-events-none = the layer never catches the click, so the whole tile stays one link. aria-hidden = screen readers skip it (the link text already says it). ?>
                        <span class="pointer-events-none absolute inset-0 flex items-end bg-gradient-to-t from-black/70 via-black/10 to-transparent opacity-0 transition-opacity duration-200 motion-reduce:transition-none group-hover:opacity-100 group-focus-visible:opacity-100" aria-hidden="true">
                            <span class="flex w-full items-center gap-2 p-2.5 text-white">
                                <?= avatar($author, 24) ?>
                                <span class="min-w-0">
                                    <span class="block truncate text-xs font-semibold leading-tight"><?= e($p['PostTitle']) ?></span>
                                    <span class="block truncate text-[11px] leading-tight opacity-90">@<?= e($p['AuthorUsername']) ?></span>
                                </span>
                            </span>
                        </span>
                    </a>
                </div>
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
