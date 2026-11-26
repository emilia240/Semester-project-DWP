<?php
/*
The small heart under a comment. Works like the heart on a post (partial like_button.php).
Needs: $c = a comment row from CommentModel::forPost() (CommentID, PostID, LikeCount, LikedByMe, AuthorShowLikes).

  visitor : a link to the Log in page (JavaScript opens the "Want to join in?" window, like the post heart).
  member  : a small form. Without JavaScript the page reloads at this comment; with JavaScript (app.js) only the heart changes.
The number is hidden when the author of the comment switched "show likes" off. The heart still works.
*/
$cid   = (int)$c['CommentID'];
$count = (int)$c['LikeCount'];
$liked = (bool)$c['LikedByMe'];
$show  = (int)$c['AuthorShowLikes'] === 1;
?>
<?php if (!logged_in()): ?>
    <a href="<?= e(url('login', ['back' => (int)$c['PostID']])) ?>" class="btn btn-ghost btn-xs gap-1 tbh-like" aria-label="Like. Log in to like this comment" data-like-guest="<?= $cid ?>">
        <?= icon('heart', 14) ?><?php if ($show && $count > 0): ?><span><?= $count ?></span><?php endif; ?>
    </a>
<?php else: ?>
    <form method="post" action="<?= e(url('like-comment')) ?>" class="inline-flex" data-like-form>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $cid ?>">
        <button type="submit" class="btn btn-ghost btn-xs gap-1 tbh-like" aria-pressed="<?= $liked ? 'true' : 'false' ?>" aria-label="Like this comment">
            <?= icon('heart', 14) ?><?php if ($show): ?><span data-like-count data-hide-zero aria-live="polite"><?= $count > 0 ? $count : '' ?></span><?php endif; ?>
        </button>
    </form>
<?php endif; ?>
