<?php
/*
PostModel  =  everything about posts that touches the database: Post, PostImage, PostCategory, PostTag.
All SQL for posts is here. The controller decides WHAT should happen, this class does the reading and writing.

A post is several rows that belong together (the post, its pictures, its categories, its tags), so create(), update()
and delete() use a TRANSACTION: either everything is saved, or nothing is.
*/
class PostModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // ---------------------------------------------------------------------
    // Reading
    // ---------------------------------------------------------------------

    // One post with its author, pictures (cover first), categories and tags. null = there is no such post.
    // $viewerId = the logged-in member (0 = a visitor). It is only used to answer "did I like this post?".
    // The three like columns are SUBQUERIES: small SELECTs inside the SELECT.
    //   LikeCount = how many likes, LikedByMe = did the viewer like it (EXISTS gives 1 or 0), AuthorShowLikes = may the number be shown.
    public function find(int $postId, int $viewerId = 0): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, u.Username AS AuthorUsername, u.FirstName AS AuthorFirstName, u.LastName AS AuthorLastName,
                    u.ShowLikes AS AuthorShowLikes,
                    (SELECT COUNT(*) FROM `PostLike` l WHERE l.PostID = p.PostID) AS LikeCount,
                    EXISTS (SELECT 1 FROM `PostLike` m WHERE m.PostID = p.PostID AND m.UserID = :me) AS LikedByMe
               FROM `Post` p
               JOIN `User` u ON u.UserID = p.UserID
              WHERE p.PostID = :id'
        );
        $stmt->bindValue(':me', $viewerId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $postId, PDO::PARAM_INT);
        $stmt->execute();
        $post = $stmt->fetch();
        if (!$post) {
            return null;
        }

        $img = $this->db->prepare('SELECT ImageID, FileName, Position FROM `PostImage` WHERE PostID = :id ORDER BY Position');
        $img->execute([':id' => $postId]);
        $post['images'] = $img->fetchAll();

        $cat = $this->db->prepare(
            'SELECT c.CategoryID, c.CategoryName
               FROM `PostCategory` pc JOIN `Category` c ON c.CategoryID = pc.CategoryID
              WHERE pc.PostID = :id ORDER BY c.CategoryName'
        );
        $cat->execute([':id' => $postId]);
        $post['categories'] = $cat->fetchAll();

        $tag = $this->db->prepare(
            'SELECT t.TagName FROM `PostTag` pt JOIN `Tag` t ON t.TagID = pt.TagID WHERE pt.PostID = :id ORDER BY t.TagName'
        );
        $tag->execute([':id' => $postId]);
        $post['tags'] = $tag->fetchAll(PDO::FETCH_COLUMN);

        return $post;
    }

    // The newest PUBLISHED posts (drafts are never in this list), one row per post with its cover picture.
    public function latest(int $limit, int $offset = 0, int $viewerId = 0): array
    {
        return $this->cards('WHERE p.IsDraft = 0', [], $limit, $offset, $viewerId);
    }

    // Count this member's new posts, including drafts, for the posting limit.
    // CreatedAt never changes when a draft is published. NOW() uses the same database clock.
    // idx_post_user_created helps the database find this member's recent posts.
    public function countCreatedSince(int $userId, int $seconds): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM `Post` WHERE `UserID` = :u AND `CreatedAt` > (NOW() - INTERVAL :s SECOND)'
        );
        $stmt->bindValue(':u', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':s', $seconds, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    // The posts of one member, newest first. Drafts are only included when the member looks at their OWN list.
    public function forUser(int $userId, bool $includeDrafts, int $viewerId = 0): array
    {
        $where = 'WHERE p.UserID = :u' . ($includeDrafts ? '' : ' AND p.IsDraft = 0');
        return $this->cards($where, [':u' => $userId], 100, 0, $viewerId);
    }

    // The shared query behind the lists: post + author + cover picture + category names and tags.
    // PostTag's primary key finds the tags for each post; Tag's primary key finds each tag name.
    // Same three like columns as find(): LikeCount, LikedByMe, AuthorShowLikes. :me appears ONCE, because the PDO setting
    // ATTR_EMULATE_PREPARES = false does not allow the same :name twice in one query.
    private function cards(string $where, array $params, int $limit, int $offset, int $viewerId = 0): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.PostID, p.UserID, p.PostTitle, p.PostDescription, p.AltText, p.UploadedAt, p.IsDraft,
                    u.Username AS AuthorUsername, u.FirstName AS AuthorFirstName, u.LastName AS AuthorLastName,
                    u.ShowLikes AS AuthorShowLikes,
                    (SELECT COUNT(*) FROM `PostLike` l WHERE l.PostID = p.PostID) AS LikeCount,
                    EXISTS (SELECT 1 FROM `PostLike` m WHERE m.PostID = p.PostID AND m.UserID = :me) AS LikedByMe,
                    i.FileName AS CoverFile,
                    (SELECT GROUP_CONCAT(c.CategoryName ORDER BY c.CategoryName SEPARATOR '|')
                       FROM `PostCategory` pc JOIN `Category` c ON c.CategoryID = pc.CategoryID
                      WHERE pc.PostID = p.PostID) AS Categories,
                    (SELECT GROUP_CONCAT(t.TagName ORDER BY t.TagName SEPARATOR '|')
                       FROM `PostTag` pt JOIN `Tag` t ON t.TagID = pt.TagID
                      WHERE pt.PostID = p.PostID) AS Tags
               FROM `Post` p
               JOIN `User` u ON u.UserID = p.UserID
               JOIN `PostImage` i ON i.PostID = p.PostID AND i.Position = 1
               $where
              ORDER BY p.UploadedAt DESC, p.PostID DESC
              LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value);
        }
        $stmt->bindValue(':me', $viewerId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // The challenge that runs today, or null. The "enter this week's challenge" box is only shown when there is one.
    public function activeChallenge(): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT ChallengeID, ChallengeName FROM `Challenge` WHERE CURDATE() BETWEEN StartDate AND EndDate ORDER BY StartDate DESC LIMIT 1'
        );
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    // Which of these category ids really exist? (the form is user input: never trust the ids)
    public function existingCategoryIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("SELECT CategoryID FROM `Category` WHERE CategoryID IN ($marks)");
        $stmt->execute(array_values($ids));
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    // ---------------------------------------------------------------------
    // Writing
    // ---------------------------------------------------------------------

    /*
    create() = save a new post with all its parts. Returns the new PostID.
    $data   title, description, alt, isDraft (bool), humanMade (bool), challengeId (int or null)
    $images file names in order: the first one is the cover (Position 1), the rest are the step photos
    */
    public function create(int $userId, array $data, array $categoryIds, array $tags, array $images): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO `Post` (UserID, ChallengeID, PostTitle, PostDescription, AltText, HumanMadeConfirmedAt, IsDraft)
                 VALUES (:u, :ch, :title, :descr, :alt, :hm, :draft)'
            );
            $stmt->execute([
                ':u'     => $userId,
                ':ch'    => $data['challengeId'],
                ':title' => $data['title'],
                ':descr' => $data['description'],
                ':alt'   => $data['alt'],
                ':hm'    => $data['humanMade'] ? date('Y-m-d H:i:s') : null,
                ':draft' => $data['isDraft'] ? 1 : 0,
            ]);
            $postId = (int)$this->db->lastInsertId();

            $this->writeParts($postId, $categoryIds, $tags, $images);
            $this->db->commit();
            return $postId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /*
    update() = save the changes of an existing post. The categories, tags and pictures are written again from scratch
    inside the transaction (simpler and safer than comparing old and new). $images is the FINAL list of file names, cover first.
    The picture FILES are not touched here: the controller deletes the replaced ones after this has succeeded.
    */
    public function update(int $postId, array $data, array $categoryIds, array $tags, array $images): void
    {
        $this->db->beginTransaction();
        try {
            // Publishing a draft: it counts as posted NOW (so it appears at the top of the feed), and the human-made tick is stamped.
            $stmt = $this->db->prepare(
                'UPDATE `Post`
                    SET ChallengeID = :ch, PostTitle = :title, PostDescription = :descr, AltText = :alt,
                        HumanMadeConfirmedAt = COALESCE(HumanMadeConfirmedAt, :hm),
                        UploadedAt = IF(IsDraft = 1 AND :draft = 0, NOW(), UploadedAt),
                        IsDraft = :draft2
                  WHERE PostID = :id'
            );
            $stmt->execute([
                ':ch'     => $data['challengeId'],
                ':title'  => $data['title'],
                ':descr'  => $data['description'],
                ':alt'    => $data['alt'],
                ':hm'     => $data['humanMade'] ? date('Y-m-d H:i:s') : null,
                ':draft'  => $data['isDraft'] ? 1 : 0,
                ':draft2' => $data['isDraft'] ? 1 : 0,
                ':id'     => $postId,
            ]);

            foreach (['PostImage', 'PostCategory', 'PostTag'] as $table) {
                $del = $this->db->prepare("DELETE FROM `$table` WHERE PostID = :id");
                $del->execute([':id' => $postId]);
            }
            $this->writeParts($postId, $categoryIds, $tags, $images);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /*
    delete() = remove the post. Its picture rows, categories, tags, likes and comments follow by themselves (ON DELETE CASCADE).
    Returns the picture FILE names, so the caller can delete the files AFTER the database row is gone:
    if a step fails, a post never points at a missing picture.
    */
    public function delete(int $postId): array
    {
        $this->db->beginTransaction();
        try {
            $names = $this->db->prepare('SELECT FileName FROM `PostImage` WHERE PostID = :id');
            $names->execute([':id' => $postId]);
            $files = $names->fetchAll(PDO::FETCH_COLUMN);

            $del = $this->db->prepare('DELETE FROM `Post` WHERE PostID = :id');
            $del->execute([':id' => $postId]);
            $this->db->commit();
            return $files;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // The three "parts" of a post, written inside the caller's transaction.
    private function writeParts(int $postId, array $categoryIds, array $tags, array $images): void
    {
        $img = $this->db->prepare('INSERT INTO `PostImage` (PostID, FileName, Position) VALUES (:p, :f, :pos)');
        foreach (array_values($images) as $i => $file) {
            $img->execute([':p' => $postId, ':f' => $file, ':pos' => $i + 1]);
        }

        $cat = $this->db->prepare('INSERT INTO `PostCategory` (PostID, CategoryID) VALUES (:p, :c)');
        foreach ($categoryIds as $categoryId) {
            $cat->execute([':p' => $postId, ':c' => $categoryId]);
        }

        // A tag word is shared by all posts: look it up, or create it. LAST_INSERT_ID(TagID) returns the id in both cases.
        $tagStmt = $this->db->prepare('INSERT INTO `Tag` (TagName) VALUES (:n) ON DUPLICATE KEY UPDATE TagID = LAST_INSERT_ID(TagID)');
        $link = $this->db->prepare('INSERT INTO `PostTag` (PostID, TagID) VALUES (:p, :t)');
        foreach ($tags as $tag) {
            $tagStmt->execute([':n' => $tag]);
            $link->execute([':p' => $postId, ':t' => (int)$this->db->lastInsertId()]);
        }
    }
}
