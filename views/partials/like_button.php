<?php
/*
The heart button. Used on post cards (front page, My account) and on the post page.
Needs: $p = a post row from PostModel (PostID, IsDraft, LikeCount, LikedByMe, AuthorShowLikes).
Optional: $back = where a member comes back to after clicking without JavaScript: 'post' (default), 'home' or 'members'.

  visitor : a link to the Log in page (with back = this post). JavaScript opens the "Want to join in?" window instead.
  member  : a small form. Without JavaScript the page reloads; with JavaScript (assets/js/app.js) only the heart changes.
  draft   : no heart (a draft is not public).
The number is hidden when the maker switched "show likes" off. The heart still works.
*/
if ($p['IsDraft']) {
    return;
}
$back  = $back ?? 'post';
$count = (int)$p['LikeCount'];
$liked = (bool)$p['LikedByMe'];
$show  = (int)$p['AuthorShowLikes'] === 1;
$id    = (int)$p['PostID'];
?>
<?php if (!logged_in()): ?>
    <a href="<?= e(url('login', ['back' => $id])) ?>" class="btn btn-ghost btn-sm gap-1 tbh-like" aria-label="Like. Log in to like this post" data-like-guest="<?= $id ?>">
        <?= icon('heart', 18) ?><?php if ($show): ?><span><?= $count ?></span><?php endif; ?>
    </a>
<?php else: ?>
    <form method="post" action="<?= e(url('like')) ?>" class="inline-flex" data-like-form>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="back" value="<?= e($back) ?>">
        <button type="submit" class="btn btn-ghost btn-sm gap-1 tbh-like" aria-pressed="<?= $liked ? 'true' : 'false' ?>" aria-label="Like">
            <?= icon('heart', 18) ?><?php if ($show): ?><span data-like-count aria-live="polite"><?= $count ?></span><?php endif; ?>
        </button>
    </form>
<?php endif; ?>
