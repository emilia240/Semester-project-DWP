<?php
/*
One post: the pictures on the left, everything about it on the right.
Needs: $post (from PostModel::find), $mine (is the logged-in user the owner), $canDelete (owner or admin),
       $savedForm (the form just saved, or null).
Likes and comments work. Saving and reporting come in later steps.
Also needs: $comments, $commentTotal, $replyTo, $oldComment, and $reportNotice (feedback shown once).
*/
$files = array_map(fn($i) => $i['FileName'], $post['images']);
$cover = $files[0];
// These facts are shared by every comment and reply on this post.
$commentCtx = [
    'postId'      => (int)$post['PostID'],
    'viewerId'    => $user === null ? 0 : (int)$user['UserID'],
    'isAdmin'     => $user !== null && $user['RoleName'] === 'admin',
    'postOwnerId' => (int)$post['UserID'],
    'replyTo'     => $replyTo,
    'old'         => $oldComment,
];
$author = ['UserID' => $post['UserID'], 'FirstName' => $post['AuthorFirstName'], 'LastName' => $post['AuthorLastName']];
?>
<section class="mx-auto max-w-[120rem] px-4 pb-16 pt-6 sm:px-16 md:px-24 lg:px-40"<?= $savedForm !== null ? ' data-saved-post-form="' . e($savedForm) . '"' : '' ?>>
    <?php // Go straight to the feed, even after several comment or report pages. ?>
    <a href="<?= e(url('home')) ?>" class="btn btn-ghost btn-sm mb-4 gap-1"><?= icon('back', 16) ?> Back</a>

    <div class="grid gap-8 lg:grid-cols-[minmax(0,1.25fr)_minmax(0,1fr)]">
        <?php // Show the cover photo and the optional step photos. ?>
        <div class="lg:sticky lg:top-24 lg:self-start">
            <figure class="overflow-hidden rounded-box bg-base-300 shadow-sm">
                <img id="post-main" src="<?= e(ImageUploader::url($cover)) ?>" <?= image_attrs($cover) ?> alt="<?= e($post['AltText'] ?? $post['PostTitle']) ?>" class="mx-auto h-auto max-h-[85vh] w-full object-contain">
            </figure>
            <?php if (count($files) > 1): ?>
                <p class="mt-3 text-sm font-bold opacity-80">Step by step · <?= count($files) ?> photos</p>
                <div class="carousel carousel-center mt-2 w-full gap-2" role="group" aria-label="Step photos">
                    <?php foreach ($files as $i => $f): ?>
                        <button type="button" class="carousel-item overflow-hidden rounded-box border-2 <?= $i === 0 ? 'border-primary' : 'border-transparent' ?>" data-thumb="<?= e(ImageUploader::url($f)) ?>" aria-label="Show photo <?= $i + 1 ?>">
                            <img src="<?= e(ImageUploader::url($f, true)) ?>" alt="" class="h-20 w-20 object-cover">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php // Show the post details and the actions this visitor may use. ?>
        <div class="min-w-0">
            <?php if ($post['IsDraft']): ?><p class="badge badge-warning font-bold">Draft · only you can see this</p><?php endif; ?>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <?php foreach ($post['categories'] as $c): ?><span class="badge badge-lg bg-base-300 border-0 font-bold"><?= e($c['CategoryName']) ?></span><?php endforeach; ?>
            </div>

            <?php // Let long words wrap instead of stretching the page sideways. ?>
            <h1 class="mt-4 wrap-anywhere text-4xl font-semibold leading-tight sm:text-5xl"><?= e($post['PostTitle']) ?></h1>

            <div class="mt-5 flex flex-wrap items-center gap-3">
                <?= avatar($author, 56) ?>
                <div class="min-w-0 flex-1">
                    <?php // Long names must fit beside the avatar and action buttons. ?>
                    <p class="wrap-anywhere font-bold"><?= e($post['AuthorFirstName'] . ' ' . $post['AuthorLastName']) ?></p>
                    <p class="wrap-anywhere text-sm opacity-80">@<?= e($post['AuthorUsername']) ?></p>
                </div>
                <?php if ($mine): ?>
                    <a href="<?= e(url('edit', ['id' => $post['PostID']])) ?>" class="btn rounded-full"><?= icon('pencil', 16) ?> Edit</a>
                <?php endif; ?>
                <?php if ($canDelete): ?>
                    <a href="<?= e(url('confirm-delete-post', ['id' => $post['PostID']])) ?>" class="btn btn-ghost rounded-full text-error" data-confirm="Delete “<?= e($post['PostTitle']) ?>”?" data-confirm-text="The photos will be removed for good." data-confirm-form="delete-form"><?= icon('trash', 16) ?> Delete</a>
                <?php endif; ?>
            </div>

            <?php if ($post['PostDescription'] !== null && $post['PostDescription'] !== ''): ?>
                <?php // Long words and links should wrap instead of stretching the page. ?>
                <p class="mt-6 wrap-anywhere text-lg leading-relaxed"><?= format_text($post['PostDescription']) ?></p>
            <?php endif; ?>

            <?php if ($post['tags']): ?>
                <?php // Each tag may also wrap inside the available width. ?>
                <p class="mt-4 flex flex-wrap gap-2"><?php foreach ($post['tags'] as $t): ?><span class="min-w-0 max-w-full wrap-anywhere font-bold text-primary">#<?= e($t) ?></span><?php endforeach; ?></p>
            <?php endif; ?>

            <p class="mt-4 text-sm opacity-80"><?= e(when($post['UploadedAt'])) ?></p>

            <div class="mt-6 flex items-center gap-1 border-y border-base-300 py-3">
                <?php partial('like_button', ['p' => $post, 'back' => 'post']); ?>
                <?php if (!$post['IsDraft']): ?><a href="#comments" class="btn btn-ghost btn-sm gap-1" aria-label="<?= (int)$commentTotal ?> comments"><?= icon('comment', 18) ?> <?= (int)$commentTotal ?></a><?php endif; ?>
                <button type="button" class="btn btn-ghost btn-sm gap-1" data-copy="<?= e(url('post', ['id' => $post['PostID']])) ?>"><?= icon('link', 18) ?> Copy link</button>
            </div>

            <?php if (!$post['IsDraft']): ?>
                <section id="comments" class="mt-8 scroll-mt-24" aria-labelledby="comments-h">
                    <h2 id="comments-h" class="text-2xl font-semibold">Comments <span class="opacity-80">(<?= (int)$commentTotal ?>)</span></h2>

                    <?php // Report feedback belongs here, where the redirect brings the member. ?>
                    <?php if ($reportNotice !== null): ?>
                        <?php $noticeClass = ['success' => 'alert-success', 'error' => 'alert-error', 'info' => 'alert-info'][$reportNotice['type']] ?? 'alert-info'; ?>
                        <div class="alert <?= e($noticeClass) ?> mt-4" role="<?= $reportNotice['type'] === 'error' ? 'alert' : 'status' ?>">
                            <span><?= e($reportNotice['message']) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($user === null): ?>
                        <div class="tbh-card mt-4 p-5 text-center">
                            <p class="font-bold">Want to ask about the technique?</p>
                            <p class="text-sm opacity-80">Only members can write comments. Everyone can read them.</p>
                            <div class="mt-3 flex justify-center gap-2"><a href="<?= e(url('join')) ?>" class="btn btn-primary btn-sm rounded-full">Join us</a><a href="<?= e(url('login', ['back' => $post['PostID']])) ?>" class="btn btn-sm rounded-full">Log in</a></div>
                        </div>
                    <?php else: ?>
                        <?php // Keep the text after a mistake so the member can correct it. ?>
                        <?php partial('comment_form', ['postId' => $post['PostID'], 'parent' => 0, 'text' => $replyTo === 0 ? $oldComment : null]); ?>
                    <?php endif; ?>

                    <?php if (!$comments): ?>
                        <p class="mt-4 opacity-80">No comments yet.</p>
                    <?php else: ?>
                        <ul class="mt-2 divide-y divide-base-300">
                            <?php foreach ($comments as $c) { partial('comment', ['c' => $c, 'ctx' => $commentCtx]); } ?>
                        </ul>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($canDelete): ?>
        <?php // JavaScript uses this form after confirmation; without it the link opens a confirmation page. ?>
        <form method="post" action="<?= e(url('delete-post')) ?>" id="delete-form" class="hidden">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$post['PostID'] ?>">
        </form>
    <?php endif; ?>
</section>
