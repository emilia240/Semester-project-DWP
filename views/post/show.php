<?php
/*
One post: the pictures on the left, everything about it on the right.
Needs: $post (from PostModel::find), $mine (is the logged-in user the owner), $canDelete (owner or admin).
Likes, comments, saving and reporting come in a later step: this page only shows what already works.
*/
$files = array_map(fn($i) => $i['FileName'], $post['images']);
$cover = $files[0];
$author = ['UserID' => $post['UserID'], 'FirstName' => $post['AuthorFirstName'], 'LastName' => $post['AuthorLastName']];
?>
<section class="mx-auto max-w-[120rem] px-4 pb-16 pt-6 sm:px-16 md:px-24 lg:px-40">
    <a href="<?= e(url('home')) ?>" class="btn btn-ghost btn-sm mb-4 gap-1" data-back><?= icon('back', 16) ?> Back</a>

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

            <h1 class="mt-4 text-4xl font-semibold leading-tight sm:text-5xl"><?= e($post['PostTitle']) ?></h1>

            <div class="mt-5 flex flex-wrap items-center gap-3">
                <?= avatar($author, 56) ?>
                <div class="min-w-0 flex-1">
                    <p class="font-bold"><?= e($post['AuthorFirstName'] . ' ' . $post['AuthorLastName']) ?></p>
                    <p class="text-sm opacity-80">@<?= e($post['AuthorUsername']) ?></p>
                </div>
                <?php if ($mine): ?>
                    <a href="<?= e(url('edit', ['id' => $post['PostID']])) ?>" class="btn rounded-full"><?= icon('pencil', 16) ?> Edit</a>
                <?php endif; ?>
                <?php if ($canDelete): ?>
                    <a href="<?= e(url('confirm-delete-post', ['id' => $post['PostID']])) ?>" class="btn btn-ghost rounded-full text-error" data-confirm="Delete “<?= e($post['PostTitle']) ?>”?" data-confirm-text="The photos will be removed for good." data-confirm-form="delete-form"><?= icon('trash', 16) ?> Delete</a>
                <?php endif; ?>
            </div>

            <?php if ($post['PostDescription'] !== null && $post['PostDescription'] !== ''): ?>
                <p class="mt-6 text-lg leading-relaxed"><?= format_text($post['PostDescription']) ?></p>
            <?php endif; ?>

            <?php if ($post['tags']): ?>
                <p class="mt-4 flex flex-wrap gap-2"><?php foreach ($post['tags'] as $t): ?><span class="font-bold text-primary">#<?= e($t) ?></span><?php endforeach; ?></p>
            <?php endif; ?>

            <p class="mt-4 text-sm opacity-80"><?= e(when($post['UploadedAt'])) ?></p>

            <div class="mt-6 flex items-center gap-1 border-y border-base-300 py-3">
                <button type="button" class="btn btn-ghost btn-sm gap-1" data-copy="<?= e(url('post', ['id' => $post['PostID']])) ?>"><?= icon('link', 18) ?> Copy link</button>
            </div>
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
