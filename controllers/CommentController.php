<?php
/*
CommentController saves a comment or a reply for a logged-in member.
It checks the post, parent, text and speed limits before asking the model to save.
The database trigger creates a notification for the right person.
*/
class CommentController extends BaseController
{
    public const MAX_LENGTH       = 1000;
    public const LIMIT_SHORT      = 6;
    public const LIMIT_SHORT_SECS = 60;
    public const LIMIT_DAY        = 100;
    private const DOUBLE_CLICK_SECS = 30;

    public function add(): void
    {
        if (!is_post()) {
            redirect_to(url('home'));
        }

        $postId   = ctype_digit(post_str('post')) ? (int)post_str('post') : 0;
        $parentId = ctype_digit(post_str('parent')) ? (int)post_str('parent') : 0;

        if (!csrf_check()) {
            flash_set('error', 'The form expired. Please try again.');
            redirect_to($postId > 0 ? url('post', ['id' => $postId]) : url('home'));
        }

        $post = $postId > 0 ? (new PostModel($this->db))->find($postId, $this->viewerId()) : null;
        if ($post === null || $post['IsDraft']) {
            $this->notFound();
            return;
        }

        $comments = new CommentModel($this->db);
        $userId   = (int)$this->user['UserID'];

        // A reply must answer a visible normal comment of this same post.
        if ($parentId > 0) {
            $parent = $comments->find($parentId);
            if ($parent === null || (int)$parent['PostID'] !== $postId || $parent['ParentCommentID'] !== null
                || !(int)$parent['AuthorVisible']) {
                $this->fail($postId, $parentId, post_str('body'), 'You cannot reply to that comment. It may have been deleted.');
            }
        }

        $text = $this->clean(post_str('body'));
        if ($text === '') {
            $this->fail($postId, $parentId, $text, 'Please write something first.');
        }
        if (mb_strlen($text) > self::MAX_LENGTH) {
            $this->fail($postId, $parentId, $text, 'A comment can be at most ' . self::MAX_LENGTH . ' characters.');
        }

        // A double click has the same text and the same parent, not just the same post.
        $same = $comments->sameRecently($userId, $postId, $parentId > 0 ? $parentId : null, $text, self::DOUBLE_CLICK_SECS);
        if ($same !== null) {
            redirect_to(url('post', ['id' => $postId]) . '#comment-' . $same);
        }

        if ($comments->countSince($userId, self::LIMIT_SHORT_SECS) >= self::LIMIT_SHORT) {
            $this->fail($postId, $parentId, $text, 'You are commenting very fast. Please wait a minute and try again.');
        }
        if ($comments->countSince($userId, 86400) >= self::LIMIT_DAY) {
            $this->fail($postId, $parentId, $text, 'You have reached the limit of ' . self::LIMIT_DAY . ' comments in 24 hours. Please come back tomorrow.');
        }

        $newId = $comments->create($postId, $userId, $parentId > 0 ? $parentId : null, $text);
        redirect_to(url('post', ['id' => $postId]) . '#comment-' . $newId);
    }

    // Normalize line breaks and remove control characters before storing the text.
    private function clean(string $text): string
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            return '';
        }
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = (string)preg_replace('/[^\P{C}\n]+/u', '', $text);
        $text = (string)preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }

    // Keep a limited amount of rejected text in the session and return to the form.
    private function fail(int $postId, int $parentId, string $text, string $message): void
    {
        flash_set('error', $message);
        $_SESSION['comment_old'] = ['post' => $postId, 'parent' => $parentId, 'text' => mb_substr($text, 0, self::MAX_LENGTH + 50)];
        $query = ['id' => $postId] + ($parentId > 0 ? ['reply' => $parentId] : []);
        redirect_to(url('post', $query) . '#' . ($parentId > 0 ? 'reply-' . $parentId : 'comment-form'));
    }

    // Show a confirmation page for people without JavaScript.
    public function confirmDelete(): void
    {
        $raw = $_GET['id'] ?? '';
        $comment = is_string($raw) && ctype_digit($raw) && $raw !== '0' ? (new CommentModel($this->db))->find((int)$raw) : null;
        if ($comment === null) {
            $this->notFound();
            return;
        }
        if (!$this->mayDelete($comment)) {
            $this->forbidden();
            return;
        }
        $this->render('comment/delete', ['title' => 'Delete comment', 'comment' => $comment]);
    }

    // Check the CSRF token and permission again before deleting anything.
    public function delete(): void
    {
        if (!is_post()) {
            redirect_to(url('home'));
        }
        if (!csrf_check()) {
            flash_set('error', 'The form expired. Please try again.');
            redirect_to(url('home'));
        }

        $comments = new CommentModel($this->db);
        $id = ctype_digit(post_str('id')) ? (int)post_str('id') : 0;
        $comment = $id > 0 ? $comments->find($id) : null;
        if ($comment === null) {
            $this->notFound();
            return;
        }
        if (!$this->mayDelete($comment)) {
            $this->forbidden();
            return;
        }

        $comments->delete($id);
        flash_set('success', 'The comment was deleted.');
        redirect_to(url('post', ['id' => $comment['PostID']]) . '#comments');
    }

    // The comment's author, the owner of the post, or an admin may delete it.
    private function mayDelete(array $comment): bool
    {
        $me = (int)$this->user['UserID'];
        return $me === (int)$comment['UserID'] || $me === (int)$comment['PostOwnerID'] || $this->user['RoleName'] === 'admin';
    }

    private function forbidden(): void
    {
        http_response_code(403);
        $this->render('pages/forbidden', ['title' => 'No access']);
    }
}
