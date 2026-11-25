<?php
/*
CommentModel = everything about comments that touches the database.
A reply is a comment with ParentCommentID pointing to the comment it answers.
The site shows one level of replies. Active bans hide comments; expired bans do not.
*/
class CommentModel
{
    private PDO $db;

    // The same rule as the post feed: an expired temporary ban does not hide an author.
    private const AUTHOR_OK = '(u.IsBanned = 0 OR u.BannedUntil <= NOW())';

    // A safety limit prevents a post with thousands of comments from making one huge page.
    private const PAGE_LIMIT = 300;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /*
    Load the visible comments of one post, oldest first, and attach replies to their parents.
    idx_comment_post_created finds the comments for the post. A reply is left out if its parent is hidden.
    The total counts only comments and replies that are actually shown, up to the limit.
    */
    public function forPost(int $postId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.CommentID, c.PostID, c.UserID, c.ParentCommentID, c.CommentText, c.CreatedAt,
                    u.Username, u.FirstName, u.LastName
               FROM `Comment` c
               JOIN `User` u ON u.UserID = c.UserID
              WHERE c.PostID = :post AND ' . self::AUTHOR_OK . '
              ORDER BY c.CreatedAt ASC, c.CommentID ASC
              LIMIT ' . self::PAGE_LIMIT
        );
        $stmt->bindValue(':post', $postId, PDO::PARAM_INT);
        $stmt->execute();

        $top = [];
        $replies = [];
        foreach ($stmt->fetchAll() as $row) {
            if ($row['ParentCommentID'] === null) {
                $row['replies'] = [];
                $top[(int)$row['CommentID']] = $row;
            } else {
                $replies[] = $row;
            }
        }
        $total = count($top);
        foreach ($replies as $reply) {
            $parent = (int)$reply['ParentCommentID'];
            if (isset($top[$parent])) {
                $top[$parent]['replies'][] = $reply;
                $total++;
            }
        }
        return ['comments' => array_values($top), 'total' => $total];
    }

    /*
    Find a comment and its post. The primary keys find the comment, post and author;
    idx_comment_parentcomment helps count its replies.
    AuthorVisible uses the same ban rule as the comment list.
    */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT c.CommentID, c.PostID, c.UserID, c.ParentCommentID, c.CommentText, c.CreatedAt,
                    u.Username, p.UserID AS PostOwnerID, p.IsDraft,
                    CASE WHEN ' . self::AUTHOR_OK . ' THEN 1 ELSE 0 END AS AuthorVisible,
                    (SELECT COUNT(*) FROM `Comment` r WHERE r.ParentCommentID = c.CommentID) AS ReplyCount
               FROM `Comment` c
               JOIN `Post` p ON p.PostID = c.PostID
               JOIN `User` u ON u.UserID = c.UserID
              WHERE c.CommentID = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    // Save a comment or a reply. The database trigger creates the notification.
    public function create(int $postId, int $userId, ?int $parentId, string $text): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO `Comment` (`PostID`, `UserID`, `ParentCommentID`, `CommentText`) VALUES (:post, :user, :parent, :text)'
        );
        $stmt->bindValue(':post', $postId, PDO::PARAM_INT);
        $stmt->bindValue(':user', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':parent', $parentId, $parentId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':text', $text, PDO::PARAM_STR);
        $stmt->execute();
        return (int)$this->db->lastInsertId();
    }

    // Delete one comment. The database also removes its replies, likes, reports and notifications.
    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM `Comment` WHERE `CommentID` = :id');
        $stmt->execute([':id' => $id]);
    }

    // idx_comment_user helps count this member's recent comments for the speed limit.
    public function countSince(int $userId, int $seconds): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM `Comment` WHERE `UserID` = :u AND `CreatedAt` > (NOW() - INTERVAL :s SECOND)'
        );
        $stmt->bindValue(':u', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':s', $seconds, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /*
    Find the same recent comment by this member, on this post and under the same parent.
    <=> also matches two NULL parents, so normal comments can be checked for double clicks.
    idx_comment_user helps find the member's comments.
    */
    public function sameRecently(int $userId, int $postId, ?int $parentId, string $text, int $seconds): ?int
    {
        $stmt = $this->db->prepare(
            'SELECT `CommentID` FROM `Comment`
              WHERE `UserID` = :u AND `PostID` = :p AND `ParentCommentID` <=> :parent
                AND `CommentText` = :t AND `CreatedAt` > (NOW() - INTERVAL :s SECOND)
              ORDER BY `CommentID` DESC LIMIT 1'
        );
        $stmt->bindValue(':u', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':p', $postId, PDO::PARAM_INT);
        $stmt->bindValue(':parent', $parentId, $parentId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':t', $text, PDO::PARAM_STR);
        $stmt->bindValue(':s', $seconds, PDO::PARAM_INT);
        $stmt->execute();
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int)$id;
    }

    /*
    The maker card shows the newest comments on one post.
    The parent and author primary keys find a reply's parent and its author.
    A reply is hidden when its parent is hidden, just like on the post page.
    */
    public function latestForPost(int $postId, int $limit = 4): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.CommentID, c.CommentText, c.CreatedAt, u.UserID, u.Username, u.FirstName, u.LastName
               FROM `Comment` c
               JOIN `User` u ON u.UserID = c.UserID
               LEFT JOIN `Comment` parent ON parent.CommentID = c.ParentCommentID
               LEFT JOIN `User` parentAuthor ON parentAuthor.UserID = parent.UserID
              WHERE c.PostID = :post AND ' . self::AUTHOR_OK . '
                AND (c.ParentCommentID IS NULL OR
                     (parentAuthor.IsBanned = 0 OR parentAuthor.BannedUntil <= NOW()))
              ORDER BY c.CreatedAt DESC, c.CommentID DESC
              LIMIT :limit'
        );
        $stmt->bindValue(':post', $postId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
