<?php
/*
A member's profile page, built from the daisy design (views/profile.php there). It only shows things;
the decisions were made in ProfileController::show.
Needs:
  $member          one row from UserModel::findProfile()   (it has no e-mail and no birthdate: those are on the My account page)
  $isOwn           true when this is MY profile
  $hidden          true when the profile is private and I may not see it
  $posts           the posts to show (rows from PostModel::forUser), already filtered by $show
  $show            'all', 'published' or 'draft' (only used on my own profile)
  $crafts          the names of the crafts the member picked
  $links           the web addresses the member put on the profile
  $publishedCount  the number of published posts
  $followers, $following   the follower numbers, $showFollowers = may they be shown
  $isFollowing     true when I already follow this member (the button then says Unfollow)
Everything that comes from the database goes through e().
Not built yet (it comes in later steps): Report, the Boards tab.
*/
$fullName = $member['FirstName'] . ' ' . $member['LastName'];
?>
<section class="mx-auto max-w-[120rem] px-4 pb-16 pt-8 sm:px-16 md:px-24 lg:px-40">

    <?php // The header card: avatar, name, username, badge, joined date, tagline, about text, crafts. ?>
    <header class="tbh-card p-6 sm:p-8">
        <div class="flex flex-col gap-6 sm:flex-row sm:items-start">
            <?= avatar($member, 120) ?>

            <div class="min-w-0 flex-1">
                <h1 class="wrap-anywhere text-4xl font-semibold leading-tight sm:text-5xl"><?= e($fullName) ?></h1>
                <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span class="wrap-anywhere font-bold">@<?= e($member['Username']) ?></span>
                    <?php // The badge is optional: it is empty (NULL) until the member picks one. ?>
                    <?php if ($member['Badge'] !== null && $member['Badge'] !== ''): ?>
                        <span class="badge badge-sm border-0 bg-secondary text-secondary-content font-semibold"><?= e($member['Badge']) ?></span>
                    <?php endif; ?>
                    <span class="text-sm opacity-80">Joined <?= e(date('j M Y', strtotime($member['CreatedAt']))) ?></span>
                </p>

                <?php if ($hidden): ?>
                    <?php // A private profile: nothing else is shown, no about text, no posts. ?>
                    <p class="mt-6 text-lg">This profile is private.</p>
                <?php else: ?>
                    <?php if ($member['Tagline'] !== null && $member['Tagline'] !== ''): ?>
                        <p class="wrap-anywhere mt-4 font-display text-2xl italic leading-snug"><?= e($member['Tagline']) ?></p>
                    <?php endif; ?>

                    <?php // The about text is shown as plain text: e() escapes everything, so no bold, italic or links. ?>
                    <?php // whitespace-pre-line keeps the new lines the member typed (HTML would otherwise join them into one line). ?>
                    <?php if ($member['AboutText'] !== null && $member['AboutText'] !== ''): ?>
                        <p class="wrap-anywhere mt-3 max-w-2xl whitespace-pre-line text-lg leading-relaxed opacity-85"><?= e($member['AboutText']) ?></p>
                    <?php endif; ?>

                    <?php if ($crafts): ?>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <?php foreach ($crafts as $craft): ?>
                                <span class="badge badge-lg border-0 bg-base-300"><?= e($craft) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php // The member's own links. data-ext makes the "do you trust this link?" warning open first (app.js). ?>
                    <?php if ($links): ?>
                        <ul class="mt-4 flex flex-wrap gap-x-6 gap-y-1">
                            <?php foreach ($links as $url): ?>
                                <?php
                                // Show the address without https:// in front (shorter to read). The link itself keeps the full address.
                                $shownUrl = str_replace(['https://', 'http://'], '', $url);
                                ?>
                                <li><a class="link link-primary wrap-anywhere inline-flex items-center gap-1" href="<?= e($url) ?>" data-ext target="_blank" rel="nofollow noopener noreferrer"><?= icon('link', 14) ?> <?= e($shownUrl) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php // The buttons: top right on a big screen, under the text on a phone. ?>
            <?php if ($isOwn): ?>
                <div class="flex shrink-0 flex-wrap items-center gap-2 sm:flex-col sm:items-stretch">
                    <a href="<?= e(url('create')) ?>" class="btn btn-primary rounded-full"><?= icon('plus', 16) ?> New post</a>
                    <a href="<?= e(url('edit-profile')) ?>" class="btn btn-outline rounded-full border-base-300"><?= icon('pencil', 16) ?> Edit profile</a>
                </div>
            <?php elseif ($hidden === false): ?>
                <?php
                // Someone else's profile: one button. It says Follow, or Unfollow when I follow this member already.
                $followText  = 'Follow';
                $followClass = 'btn btn-primary rounded-full';
                if ($isFollowing) {
                    $followText  = 'Unfollow';
                    $followClass = 'btn btn-outline rounded-full border-base-300';
                }
                ?>
                <div class="flex shrink-0 flex-wrap items-center gap-2 sm:flex-col sm:items-stretch">
                    <?php // A small form: it sends the id of THIS member. Who is following is taken from the login in PHP. ?>
                    <form method="post" action="<?= e(url('follow')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= e((string)$member['UserID']) ?>">
                        <button type="submit" class="<?= e($followClass) ?>"><?= e($followText) ?></button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <?php // The numbers: posts, followers, following. The owner can hide the follower numbers from other people. ?>
        <?php if ($hidden === false): ?>
            <?php if ($showFollowers): ?>
                <div class="mt-7 grid grid-cols-3 divide-x divide-base-300 border-t border-base-300 pt-5 text-center">
                    <div><p class="font-display text-3xl font-semibold"><?= e((string)$publishedCount) ?></p><p class="text-sm opacity-80">posts</p></div>
                    <div><p class="font-display text-3xl font-semibold"><?= e(number_format($followers)) ?></p><p class="text-sm opacity-80">followers</p></div>
                    <div><p class="font-display text-3xl font-semibold"><?= e((string)$following) ?></p><p class="text-sm opacity-80">following</p></div>
                </div>
            <?php else: ?>
                <div class="mt-7 grid grid-cols-1 border-t border-base-300 pt-5 text-center">
                    <div><p class="font-display text-3xl font-semibold"><?= e((string)$publishedCount) ?></p><p class="text-sm opacity-80">posts</p></div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </header>

    <?php if ($hidden === false): ?>

        <h2 class="mt-10 border-b border-base-300 pb-2 text-base font-bold">Posts</h2>

        <?php if ($isOwn): ?>
            <?php // The filter buttons. The current one is dark. ?>
            <div class="mt-5 flex flex-wrap items-center gap-2">
                <?php
                $filters = ['all' => 'All', 'published' => 'Published', 'draft' => 'Drafts'];
                foreach ($filters as $key => $text):
                    $buttonClass = 'btn btn-sm rounded-full btn-outline border-base-300';
                    if ($show === $key) {
                        $buttonClass = 'btn btn-sm rounded-full btn-neutral';
                    }
                ?>
                    <a href="<?= e(url('profile', ['show' => $key])) ?>" class="<?= e($buttonClass) ?>"><?= e($text) ?></a>
                <?php endforeach; ?>
                <span class="text-sm opacity-80">Drafts are only visible to you.</span>
            </div>
        <?php endif; ?>

        <?php if ($posts): ?>
            <div class="mt-6 columns-2 gap-5 md:columns-3 lg:columns-4">
                <?php foreach ($posts as $p): ?>
                    <?php if ($isOwn): ?>
                        <?php
                        // On my own profile every card gets a Draft or Published label and Edit / Delete links.
                        // A draft opens the edit page (a draft has no public post page).
                        $isDraft    = ((int)$p['IsDraft'] === 1);
                        $label      = 'Published';
                        $labelClass = 'badge-success';
                        $href       = url('post', ['id' => $p['PostID']]);
                        if ($isDraft) {
                            $label      = 'Draft';
                            $labelClass = 'badge-warning';
                            $href       = url('edit', ['id' => $p['PostID']]);
                        }
                        ?>
                        <div class="mb-2 break-inside-avoid">
                            <?php partial('mini_card', ['p' => $p, 'label' => $label, 'labelClass' => $labelClass, 'href' => $href]); ?>
                            <div class="-mt-3 mb-5 flex gap-2">
                                <a href="<?= e(url('edit', ['id' => $p['PostID']])) ?>" class="btn btn-xs rounded-full"><?= icon('pencil', 12) ?> Edit</a>
                                <a href="<?= e(url('confirm-delete-post', ['id' => $p['PostID']])) ?>" class="btn btn-ghost btn-xs rounded-full"><?= icon('trash', 12) ?> Delete</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php partial('mini_card', ['p' => $p]); ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="tbh-card mt-6 p-10 text-center">
                <p class="font-display text-2xl font-semibold">No posts here yet</p>
                <?php if ($isOwn): ?>
                    <p class="mt-2 opacity-80">Your first post can be small and a little crooked. That is the point.</p>
                    <a href="<?= e(url('create')) ?>" class="btn btn-primary mt-5 rounded-full">Create a post</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</section>
