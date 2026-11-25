<?php
/*
One comment and its replies (one level), as a list item.
Needs: $c = a comment row from CommentModel::forPost() (with its 'replies'), $isReply (true for a reply).
Text goes through format_text(): everything is escaped first, then only bold, italic, links and #tags become HTML.
*/
$isReply = $isReply ?? false;
?>
<li class="<?= $isReply ? 'ml-6 mt-4 sm:ml-10' : 'py-5' ?> scroll-mt-24" id="comment-<?= (int)$c['CommentID'] ?>">
    <div class="flex items-start gap-3">
        <?= avatar($c, $isReply ? 36 : 40) ?>
        <div class="min-w-0 flex-1">
            <p class="wrap-anywhere text-sm"><span class="font-bold">@<?= e($c['Username']) ?></span> <span class="opacity-80">· <?= e(when($c['CreatedAt'])) ?></span></p>
            <?php // Long words and links wrap instead of stretching the page. ?>
            <p class="mt-1 wrap-anywhere leading-relaxed"><?= format_text($c['CommentText']) ?></p>
        </div>
    </div>
    <?php if (!$isReply && !empty($c['replies'])): ?>
        <ul>
            <?php foreach ($c['replies'] as $reply) { partial('comment', ['c' => $reply, 'isReply' => true]); } ?>
        </ul>
    <?php endif; ?>
</li>
