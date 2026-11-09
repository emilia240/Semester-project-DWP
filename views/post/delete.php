<?php
/*
The confirmation page for deleting a post.
It is the fallback for members and admins who do not use JavaScript.
*/
?>
<section class="mx-auto max-w-xl px-4 pb-16 pt-10">
    <h1 class="text-4xl font-semibold">Delete this post?</h1>
    <p class="mt-4">Are you sure you want to delete "<?= e($post['PostTitle']) ?>"? The photos will be removed for good.</p>

    <?php // A POST form with a CSRF token prevents deletion through a plain link. ?>
    <form method="post" action="<?= e(url('delete-post')) ?>" class="mt-6 flex flex-wrap gap-3">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$post['PostID'] ?>">
        <a href="<?= e(url('post', ['id' => $post['PostID']])) ?>" class="btn rounded-full">Keep it</a>
        <button type="submit" class="btn btn-error rounded-full">Yes, delete</button>
    </form>
</section>
