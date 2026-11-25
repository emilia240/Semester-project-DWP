<?php
/*
One comment and its replies (one level), as a list item.
Needs: $c = a comment row from CommentModel::forPost() (with its 'replies'), $isReply (true for a reply),
       $postId, $loggedIn, $replyTo (the comment with an open reply box), and $old (text to refill).
Text goes through format_text(): everything is escaped first, then only bold, italic, links and #tags become HTML.
*/
$isReply  = $isReply ?? false;
$loggedIn = $loggedIn ?? false;
$replyTo  = (int)($replyTo ?? 0);
$cid      = (int)$c['CommentID'];
?>
<li class="<?= $isReply ? 'ml-6 mt-4 sm:ml-10' : 'py-5' ?> scroll-mt-24" id="comment-<?= (int)$c['CommentID'] ?>">
    <div class="flex items-start gap-3">
        <?= avatar($c, $isReply ? 36 : 40) ?>
        <div class="min-w-0 flex-1">
            <p class="wrap-anywhere text-sm"><span class="font-bold">@<?= e($c['Username']) ?></span> <span class="opacity-80">· <?= e(when($c['CreatedAt'])) ?></span></p>
            <?php // Long words and links wrap instead of stretching the page. ?>
            <p class="mt-1 wrap-anywhere leading-relaxed"><?= format_text($c['CommentText']) ?></p>
            <?php // A link opens the reply box even when JavaScript is off. ?>
            <?php if ($loggedIn && !$isReply): ?>
                <div class="mt-1 flex flex-wrap items-center gap-1 text-sm">
                    <a href="<?= e(url('post', ['id' => $postId, 'reply' => $cid]) . '#reply-' . $cid) ?>" class="btn btn-ghost btn-xs gap-1"><?= icon('reply', 14) ?> Reply</a>
                </div>
            <?php endif; ?>
            <?php if ($loggedIn && !$isReply && $replyTo === $cid): ?>
                <div id="reply-<?= $cid ?>" class="scroll-mt-24">
                    <?php partial('comment_form', ['postId' => $postId, 'parent' => $cid, 'text' => $old, 'focus' => true]); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!$isReply && !empty($c['replies'])): ?>
        <ul>
            <?php foreach ($c['replies'] as $reply) { partial('comment', ['c' => $reply, 'isReply' => true, 'postId' => $postId, 'loggedIn' => $loggedIn]); } ?>
        </ul>
    <?php endif; ?>
</li>
