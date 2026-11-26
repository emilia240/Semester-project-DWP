<?php
/*
LikeController  =  the heart button: like a post or a comment, or take the like back.
Routes: index.php?page=like (a post) and index.php?page=like-comment (a comment). POST only, members only:
the route table in index.php already checked that you are logged in.

What likeOrUnlike() checks, in this order:
  1. it must be a POST            (a link or an address typed by hand does nothing)
  2. the CSRF token must be right (a form on another website cannot like for you)
  3. the id is digits only, and the post exists AND is published (a draft gives "not found", like a missing post)
  4. LikeModel::likeOrUnlike(), then count again
  5. answer: JavaScript asks for JSON and gets {"ok":true,"liked":true,"count":3}; a plain form gets a redirect back
*/
class LikeController extends BaseController
{
    // Where the form may send you back to. The form only says WHICH of these, never a web address.
    private const BACK = ['post', 'home', 'members'];

    public function likeOrUnlike(): void
    {
        $json = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

        if (!is_post()) {
            redirect_to(url('home'));
        }

        $id   = ctype_digit(post_str('id')) ? (int)post_str('id') : 0;
        $back = in_array(post_str('back'), self::BACK, true) ? post_str('back') : 'post';

        if (!csrf_check()) {
            $this->answer($json, 403, ['error' => 'The form expired. Please try again.'], $back, $id, 'The form expired. Please try again.');
            return;
        }

        $likes  = new LikeModel($this->db);
        $userId = (int)$this->user['UserID'];

        if ($id < 1 || $likes->summary($id, $userId) === null) {
            $this->answer($json, 404, ['error' => 'That post was not found.'], 'home', 0, 'That post was not found.');
            return;
        }

        $liked = $likes->likeOrUnlike($userId, $id);
        $now   = $likes->summary($id, $userId);

        // The number is only sent when the maker allows it, so a hidden number cannot be read from this answer either.
        $count = ($now !== null && $now['showCount']) ? $now['count'] : null;

        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'liked' => $liked, 'count' => $count]);
            return;
        }
        redirect_to($this->backUrl($back, $id));
    }

    /*
    The heart under a comment. Same steps as likeOrUnlike(), but the id is a COMMENT id:
    POST only, CSRF, digits only, the comment must be visible (published post, author not banned), then like or unlike and count again.
    JavaScript gets {"ok":true,"liked":true,"count":3}; a plain form gets a redirect back to that comment.
    */
    public function likeOrUnlikeComment(): void
    {
        $json = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

        if (!is_post()) {
            redirect_to(url('home'));
        }

        $id = ctype_digit(post_str('id')) ? (int)post_str('id') : 0;

        if (!csrf_check()) {
            $this->failComment($json, 403, 'The form expired. Please try again.');
            return;
        }

        $likes  = new LikeModel($this->db);
        $userId = (int)$this->user['UserID'];

        if ($id < 1 || ($before = $likes->commentSummary($id, $userId)) === null) {
            $this->failComment($json, 404, 'That comment was not found.');
            return;
        }

        $liked = $likes->likeOrUnlikeComment($userId, $id);
        $now   = $likes->commentSummary($id, $userId);

        // The number is only sent when the comment's author allows it (same switch as for posts).
        $count = ($now !== null && $now['showCount']) ? $now['count'] : null;

        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'liked' => $liked, 'count' => $count]);
            return;
        }
        redirect_to(url('post', ['id' => $before['postId']]) . '#comment-' . $id);
    }

    // An error for the comment heart: JSON for JavaScript, otherwise a message on the front page (we may not know the post).
    private function failComment(bool $json, int $status, string $message): void
    {
        if ($json) {
            http_response_code($status);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => $message]);
            return;
        }
        flash_set('error', $message);
        redirect_to(url('home'));
    }

    // An error answer: JSON for JavaScript, otherwise a message on the page we came from.
    private function answer(bool $json, int $status, array $data, string $back, int $id, string $message): void
    {
        if ($json) {
            http_response_code($status);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false] + $data);
            return;
        }
        flash_set('error', $message);
        redirect_to($this->backUrl($back, $id));
    }

    private function backUrl(string $back, int $id): string
    {
        if ($back === 'post' && $id > 0) {
            return url('post', ['id' => $id]);
        }
        return url($back === 'members' ? 'members' : 'home');
    }
}
