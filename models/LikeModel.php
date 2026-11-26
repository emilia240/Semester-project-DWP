<?php
/*
LikeModel  =  everything about likes that touches the database: the tables PostLike and CommentLike.
One row in PostLike = one member likes one post. One row in CommentLike = one member likes one comment. The PRIMARY KEY is (UserID, PostID), so the database itself
refuses a second row for the same pair. That is how a double like is stopped, even if someone double-clicks very fast.
*/
class LikeModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /*
    The numbers one heart needs: how many likes, did THIS member like it, may the number be shown.
    Returns null when there is no such post OR the post is a draft: nobody can like what is not public.
    $userId = 0 means a visitor (nobody has user id 0, so "liked by me" is simply false).
    */
    public function summary(int $postId, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT (SELECT COUNT(*) FROM `PostLike` l WHERE l.PostID = p.PostID) AS LikeCount,
                    EXISTS (SELECT 1 FROM `PostLike` m WHERE m.PostID = p.PostID AND m.UserID = :me) AS LikedByMe,
                    u.ShowLikes
               FROM `Post` p
               JOIN `User` u ON u.UserID = p.UserID
              WHERE p.PostID = :id AND p.IsDraft = 0'
        );
        $stmt->bindValue(':me', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $postId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        return [
            'count'     => (int)$row['LikeCount'],
            'liked'     => (bool)$row['LikedByMe'],
            'showCount' => (int)$row['ShowLikes'] === 1,
        ];
    }

    /*
    One button, two meanings: like, or take the like back.
    First try to DELETE the row. If a row was deleted, it was an unlike. If not, INSERT it.
    Returns true when the post is liked now, false when it is not liked now.
    */
    public function likeOrUnlike(int $userId, int $postId): bool
    {
        $del = $this->db->prepare('DELETE FROM `PostLike` WHERE UserID = :u AND PostID = :p');
        $del->execute([':u' => $userId, ':p' => $postId]);
        if ($del->rowCount() > 0) {
            return false;
        }

        try {
            $ins = $this->db->prepare('INSERT INTO `PostLike` (UserID, PostID) VALUES (:u, :p)');
            $ins->execute([':u' => $userId, ':p' => $postId]);
        } catch (PDOException $e) {
            // 1062 = "duplicate entry". Two clicks arrived at the same moment and the other one won:
            // the post IS liked, so that is fine. Any other database error is a real problem: pass it on.
            if ((int)($e->errorInfo[1] ?? 0) !== 1062) {
                throw $e;
            }
        }
        return true;
    }

    /*
    The same three numbers for ONE COMMENT: how many likes, did THIS member like it, may the number be shown
    (the author of the comment decides, with the same "show likes" switch as for their posts), and which post it is under.
    Returns null when there is no such comment, when its post is a draft, or when its author is banned right now:
    a comment nobody can see cannot be liked. $userId = 0 means a visitor.
    */
    public function commentSummary(int $commentId, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT c.PostID,
                    (SELECT COUNT(*) FROM `CommentLike` l WHERE l.CommentID = c.CommentID) AS LikeCount,
                    EXISTS (SELECT 1 FROM `CommentLike` m WHERE m.CommentID = c.CommentID AND m.UserID = :me) AS LikedByMe,
                    u.ShowLikes
               FROM `Comment` c
               JOIN `Post` p ON p.PostID = c.PostID
               JOIN `User` u ON u.UserID = c.UserID
              WHERE c.CommentID = :id AND p.IsDraft = 0 AND (u.IsBanned = 0 OR u.BannedUntil <= NOW())'
        );
        $stmt->bindValue(':me', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $commentId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        return [
            'postId'    => (int)$row['PostID'],
            'count'     => (int)$row['LikeCount'],
            'liked'     => (bool)$row['LikedByMe'],
            'showCount' => (int)$row['ShowLikes'] === 1,
        ];
    }

    /*
    Like a comment, or take the like back: the same trick as for posts. Try to DELETE the row; if one was deleted it was an
    unlike, otherwise INSERT. The PRIMARY KEY (UserID, CommentID) refuses a second row, so a double click cannot count twice.
    Returns true when the comment is liked now.
    */
    public function likeOrUnlikeComment(int $userId, int $commentId): bool
    {
        $del = $this->db->prepare('DELETE FROM `CommentLike` WHERE UserID = :u AND CommentID = :c');
        $del->execute([':u' => $userId, ':c' => $commentId]);
        if ($del->rowCount() > 0) {
            return false;
        }

        try {
            $ins = $this->db->prepare('INSERT INTO `CommentLike` (UserID, CommentID) VALUES (:u, :c)');
            $ins->execute([':u' => $userId, ':c' => $commentId]);
        } catch (PDOException $e) {
            // 1062 = "duplicate entry": the other of two quick clicks won, so the comment IS liked. Anything else is a real error.
            if ((int)($e->errorInfo[1] ?? 0) !== 1062) {
                throw $e;
            }
        }
        return true;
    }
}
