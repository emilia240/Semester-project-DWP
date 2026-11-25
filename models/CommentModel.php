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
