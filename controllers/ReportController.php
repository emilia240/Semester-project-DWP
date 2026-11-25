<?php
/*
ReportController lets a member report another person's comment.
It validates the reason and speed limit, then asks ReportModel to save the report.
The report page also works without JavaScript.
*/
class ReportController extends BaseController
{
    public const REASONS = [
        'ai'     => 'Looks AI generated',
        'spam'   => 'Spam or ads',
        'unkind' => 'Unkind or hurtful',
        'other'  => 'Something else',
    ];
    public const MAX_DETAILS = 300;
    private const LIMIT_HOUR = 10;

    public function comment(): void
    {
        $raw = is_post() ? post_str('id') : ($_GET['id'] ?? '');
        $comments = new CommentModel($this->db);
        $comment = is_string($raw) && ctype_digit($raw) && $raw !== '0' && strlen($raw) <= 10 ? $comments->find((int)$raw) : null;
        if ($comment === null || $comment['IsDraft']) {
            $this->notFound();
            return;
        }

        $commentId = (int)$comment['CommentID'];
        $back = url('post', ['id' => $comment['PostID']]) . '#comment-' . $commentId;
        $me = (int)$this->user['UserID'];
        if ((int)$comment['UserID'] === $me) {
            $this->returnToComments((int)$comment['PostID'], 'error', 'You cannot report your own comment.');
        }

        $reports = new ReportModel($this->db);
        $error = null;
        $old = ['reason' => '', 'details' => ''];
        if (is_post()) {
            $old = ['reason' => post_str('reason'), 'details' => post_str('details')];
            if (!csrf_check()) {
                $error = 'The form expired. Please try again.';
            } elseif (!isset(self::REASONS[$old['reason']])) {
                $error = 'Please pick a reason.';
            } elseif (mb_strlen($old['details']) > self::MAX_DETAILS) {
                $error = 'Please keep it to ' . self::MAX_DETAILS . ' characters.';
            } elseif ($old['reason'] === 'other' && $old['details'] === '') {
                $error = 'Please tell us a little more.';
            } elseif ($reports->commentReportedBy($me, $commentId)) {
                $this->returnToComments((int)$comment['PostID'], 'info', 'You already reported this comment. Thank you, a moderator will look at it.');
            } elseif ($reports->countSince($me, 3600) >= self::LIMIT_HOUR) {
                $error = 'You have sent a lot of reports. Please try again later.';
            } else {
                $reason = self::REASONS[$old['reason']] . ($old['details'] !== '' ? ': ' . $old['details'] : '');
                $reports->createForComment($me, $commentId, $reason);
                $this->returnToComments((int)$comment['PostID'], 'success', 'Thank you. A moderator will look at it.');
            }

            // After a POST error, keep the values once and redirect to the normal form page.
            $_SESSION['report_old'] = ['commentId' => $commentId, 'values' => $old, 'error' => $error];
            redirect_to(url('report-comment', ['id' => $commentId]));
        }

        // Only restore a failed form for the same comment, and only once.
        $saved = $_SESSION['report_old'] ?? null;
        if (is_array($saved) && ($saved['commentId'] ?? null) === $commentId) {
            $old = $saved['values'];
            $error = $saved['error'];
            unset($_SESSION['report_old']);
        }
        $this->render('report/comment', [
            'title'   => 'Report a comment',
            'comment' => $comment,
            'reasons' => self::REASONS,
            'old'     => $old,
            'error'   => $error,
            'back'    => $back,
        ]);
    }

    // Keep report feedback until the post opens, then land beside the comments instead of below the message.
    private function returnToComments(int $postId, string $type, string $message): void
    {
        $_SESSION['report_notice'] = ['postId' => $postId, 'type' => $type, 'message' => $message];
        redirect_to(url('post', ['id' => $postId]) . '#comments');
    }
}
