<?php
/*
PostController  =  the pages about one post.

  create       the form to make a post, and saving it           (members)     FigJam: "Create a post" (Flow 16)
  edit         the same form, filled in, for your own post      (members)     FigJam: "Edit post" (Flow 5)
  show         one post, with all its pictures                  (everybody)
  delete       remove a post and its picture files              (owner or admin, POST only)

The rules that matter (the buttons being hidden is NOT protection, so each of these is checked here, in PHP):
  - the form must carry the CSRF token
  - only the owner may edit; the owner or an admin may delete; a draft is invisible to everybody else
  - the pictures are checked by ImageUploader BEFORE anything is written to the database
  - if saving fails halfway, the picture files that were just written are deleted again
*/
class PostController extends BaseController
{
    public const MAX_CATEGORIES = 3;
    public const MAX_STEP_PHOTOS = 6;     // plus the cover = 7 pictures per post
    public const MAX_TAGS = 8;

    // A valid CSRF token cannot stop a member from replaying their own request.
    // Count new posts instead. Drafts count too; editing is not limited.
    public const NEW_POSTS_SHORT         = 5;         // at most 5 new posts ...
    public const NEW_POSTS_SHORT_SECONDS = 600;       // ... in 10 minutes
    public const NEW_POSTS_DAY           = 20;        // and at most 20 in 24 hours

    // =================================================================
    //   One post
    // =================================================================
    public function show(): void
    {
        $post = $this->findFromQuery();
        // A draft belongs to its owner. Everybody else gets "not found", not "forbidden", so they cannot even tell it exists.
        if ($post === null || ($post['IsDraft'] && !$this->isOwner($post))) {
            $this->notFound();
            return;
        }
        // Only the matching successful save can tell this tab to skip its old form on Back.
        $savedForm = null;
        $savedToken = $_GET['saved'] ?? null;
        $saved = $_SESSION['post_save_back'] ?? null;
        if ($this->isOwner($post) && is_string($savedToken) && is_array($saved)
            && isset($saved['token'], $saved['postId'], $saved['form'])
            && is_string($saved['token']) && is_string($saved['form'])
            && $saved['postId'] === (int)$post['PostID']
            && hash_equals($saved['token'], $savedToken)) {
            $savedForm = $saved['form'];
            unset($_SESSION['post_save_back']);
        }
        // The comments of a published post, as a tree (a draft has none).
        $thread = $post['IsDraft'] ? ['comments' => [], 'total' => 0] : (new CommentModel($this->db))->forPost((int)$post['PostID'], $this->viewerId());
        // ?edit=12 opens the edit box in place of comment 12, but only for its author and only when it is visible on this post.
        $editId = 0;
        $rawEdit = $_GET['edit'] ?? '';
        if ($this->user !== null && is_string($rawEdit) && ctype_digit($rawEdit) && strlen($rawEdit) <= 10) {
            foreach ($thread['comments'] as $c) {
                foreach (array_merge([$c], $c['replies']) as $x) {
                    if ((int)$x['CommentID'] === (int)$rawEdit && (int)$x['UserID'] === (int)$this->user['UserID']) {
                        $editId = (int)$rawEdit;
                    }
                }
            }
        }
        // The text of a failed edit comes back once, only into the same comment's box.
        $editOld = null;
        $oldEdit = $_SESSION['comment_edit_old'] ?? null;
        if (is_array($oldEdit)) {
            if ($editId > 0 && (int)($oldEdit['id'] ?? 0) === $editId && is_string($oldEdit['text'] ?? null)) {
                $editOld = $oldEdit['text'];
            }
            unset($_SESSION['comment_edit_old']);
        }
        // A reply box only opens for a normal comment visible on this post.
        $replyTo = 0;
        $raw = $_GET['reply'] ?? '';
        if ($this->user !== null && is_string($raw) && ctype_digit($raw) && strlen($raw) <= 10) {
            foreach ($thread['comments'] as $c) {
                if ((int)$c['CommentID'] === (int)$raw) {
                    $replyTo = (int)$raw;
                }
            }
        }
        // Keep rejected text once, on the same post and in the matching box.
        $oldComment = null;
        $old = $_SESSION['comment_old'] ?? null;
        if (is_array($old) && (int)($old['post'] ?? 0) === (int)$post['PostID']) {
            $oldComment = is_string($old['text'] ?? null) ? $old['text'] : null;
            $replyTo = (int)($old['parent'] ?? 0) > 0 ? $replyTo : 0;
            unset($_SESSION['comment_old']);
        }
        // One box at a time: while a comment is being edited, no reply box is open.
        if ($editId > 0) {
            $replyTo = 0;
        }
        // Show report feedback once, only beside the comments on the matching post.
        $reportNotice = null;
        $notice = $_SESSION['report_notice'] ?? null;
        if (is_array($notice) && ($notice['postId'] ?? null) === (int)$post['PostID'] && !$post['IsDraft']) {
            $reportNotice = $notice;
            unset($_SESSION['report_notice']);
        }
        // The Follow button next to the author. These two answers are sent to the view:
        //   $showFollow  = true when the button should be there at all
        //   $isFollowing = true when I follow the author already (the button then says Unfollow)
        $showFollow  = false;
        $isFollowing = false;
        // On my own post there is no button (nobody follows themselves).
        if ($this->isOwner($post) === false) {
            $users  = new UserModel($this->db);
            $author = $users->findProfile((int)$post['UserID']);
            if ($author !== null) {
                // The same rules as the Follow button on the profile page (ProfileController::follow):
                // a banned member cannot be followed, and a private profile cannot be followed (only an admin may).
                $authorIsPublic = false;
                if ((int)$author['IsProfilePublic'] === 1) {
                    $authorIsPublic = true;
                }
                $authorIsBanned = is_banned($author);
                if ($authorIsBanned === false && ($authorIsPublic || $this->isAdmin())) {
                    $showFollow = true;
                }
            }
            // Only a logged-in member can already follow someone. A visitor sees the button, but it leads to the login page.
            if ($showFollow && $this->user !== null) {
                $isFollowing = $users->isFollowing((int)$this->user['UserID'], (int)$post['UserID']);
            }
        }

        $this->render('post/show', [
            'title' => $post['PostTitle'],
            'post'  => $post,
            'showFollow'   => $showFollow,
            'isFollowing'  => $isFollowing,
            'comments'     => $thread['comments'],
            'commentTotal' => $thread['total'],
            'replyTo'      => $replyTo,
            'oldComment'   => $oldComment,
            'editId'       => $editId,
            'editOld'      => $editOld,
            'reportNotice' => $reportNotice,
            'mine'  => $this->isOwner($post),
            'canDelete' => $this->isOwner($post) || $this->isAdmin(),
            'savedForm' => $savedForm,
        ]);
    }

    // =================================================================
    //   New post / edit post (one form for both)
    // =================================================================
    public function create(): void
    {
        $this->form(null);
    }

    public function edit(): void
    {
        $post = $this->findFromQuery();
        if ($post === null || ($post['IsDraft'] && !$this->isOwner($post))) {
            $this->notFound();
            return;
        }
        if (!$this->isOwner($post)) {                 // somebody typed the address of another member's post
            http_response_code(403);
            $this->render('pages/forbidden', ['title' => 'No access']);
            return;
        }
        $this->form($post);
    }

    // $post = the post being edited, or null for a new one.
    private function form(?array $post): void
    {
        $posts      = new PostModel($this->db);
        $categories = (new CategoryModel($this->db))->all();
        $challenge  = $posts->activeChallenge();
        $errors     = [];
        $old        = $this->valuesFromPost($post);

        if (is_post()) {
            if (ImageUploader::requestTooBig()) {
                // Over post_max_size PHP throws away the WHOLE form (also the CSRF token), so this must be checked FIRST,
                // or the visitor would be told "the form expired", which is not true.
                $errors['form'] = 'The pictures together are too big to send in one go. Try fewer or smaller pictures.';

            } elseif (!csrf_check()) {
                $errors['form'] = 'The form expired. Please try again.';

            } else {
                $old = $this->valuesFromForm($post);
                // Check the limit only after CSRF, and only for a new post.
                $limitMessage = $post === null ? $this->postingLimitMessage($posts) : null;
                if ($limitMessage !== null) {
                    // 429 means too many requests. The text stays; photos must be chosen again.
                    http_response_code(429);
                    $errors['form'] = $limitMessage;
                } else {
                    $errors = $this->validate($old, $post, $posts, $challenge);
                    if (!$errors) {
                        $errors = $this->save($posts, $post, $old, $challenge);
                    }
                }
            }
        }

        $this->render('post/form', [
            'title'      => $post ? 'Edit your post' : 'New post',
            'edit'       => $post,
            'old'        => $old,
            'errors'     => $errors,
            'categories' => $categories,
            'challenge'  => $challenge,
            'fileLimit'  => ImageUploader::fileLimitText(),
        ]);
    }

    // The values to show in the form when it is opened (not submitted yet).
    private function valuesFromPost(?array $post): array
    {
        if ($post === null) {
            return ['title' => '', 'description' => '', 'alt' => '', 'categories' => [], 'tags' => [], 'humanMade' => false, 'challenge' => false, 'isDraft' => false, 'remove' => []];
        }
        return [
            'title'       => $post['PostTitle'],
            'description' => (string)$post['PostDescription'],
            'alt'         => (string)$post['AltText'],
            'categories'  => array_map(fn($c) => (int)$c['CategoryID'], $post['categories']),
            'tags'        => $post['tags'],
            'humanMade'   => $post['HumanMadeConfirmedAt'] !== null,
            'challenge'   => $post['ChallengeID'] !== null,
            'isDraft'     => (bool)$post['IsDraft'],
            'remove'      => [],
        ];
    }

    // Read the submitted form. Everything here is user input: nothing is trusted yet.
    private function valuesFromForm(?array $post): array
    {
        $categoryIds = [];
        foreach ((array)($_POST['categories'] ?? []) as $id) {
            if (is_string($id) && ctype_digit($id)) {
                $categoryIds[(int)$id] = (int)$id;
            }
        }

        $tags = [];
        foreach (explode(',', post_str('tags')) as $raw) {
            $tag = mb_strtolower(ltrim(trim($raw), '#'));
            $tag = mb_substr((string)preg_replace('/[^a-z0-9_\-æøå]/u', '', $tag), 0, 30);
            if ($tag !== '') {
                $tags[$tag] = $tag;
            }
        }

        $remove = [];
        foreach ((array)($_POST['remove'] ?? []) as $id) {
            if (is_string($id) && ctype_digit($id)) {
                $remove[(int)$id] = (int)$id;
            }
        }

        return [
            'title'       => post_str('title'),
            'description' => post_str('description'),
            'alt'         => post_str('alt'),
            'categories'  => array_values($categoryIds),
            'tags'        => array_slice(array_values($tags), 0, self::MAX_TAGS),
            'humanMade'   => isset($_POST['human_made']),
            'challenge'   => isset($_POST['challenge']),
            // a post that is already published cannot be turned back into a draft (the button is hidden; this stops a hand-made request)
            'isDraft'     => post_str('action') === 'draft' && ($post === null || (bool)$post['IsDraft']),
            'remove'      => array_values($remove),
        ];
    }

    // The text and the counting rules. Cheap checks first: no picture is opened until all of these are fine.
    private function validate(array $v, ?array $post, PostModel $posts, ?array $challenge): array
    {
        $errors = [];
        $publishing = !$v['isDraft'];

        if (mb_strlen($v['title']) > 100) {
            $errors['title'] = 'The title can be at most 100 characters.';
        } elseif ($publishing && $v['title'] === '') {
            $errors['title'] = 'Please give your post a title.';
        }
        if (mb_strlen($v['description']) > 5000) {
            $errors['description'] = 'The story can be at most 5000 characters.';
        }
        if (mb_strlen($v['alt']) > 200) {
            $errors['alt'] = 'The picture description can be at most 200 characters.';
        } elseif ($publishing && $v['alt'] === '') {
            $errors['alt'] = 'Please describe the picture for people who cannot see it.';
        }

        if (count($v['categories']) > self::MAX_CATEGORIES) {
            $errors['categories'] = 'Pick at most ' . self::MAX_CATEGORIES . ' crafts.';
        } elseif ($publishing && $v['categories'] === []) {
            $errors['categories'] = 'Pick at least one craft.';
        } elseif (count($posts->existingCategoryIds($v['categories'])) !== count($v['categories'])) {
            $errors['categories'] = 'Please pick crafts from the list.';
        }

        if ($publishing && !$v['humanMade']) {
            $errors['human_made'] = 'Please tick the box: only work you made yourself, by hand.';
        }

        // ---- the pictures: how many? (counted BEFORE any of them is opened) ----
        $cover = $_FILES['photo'] ?? null;
        $steps = ImageUploader::many($_FILES['steps'] ?? null);

        if ($post === null && !ImageUploader::chosen($cover)) {
            $errors['photo'] = 'Please add a photo of your piece.';
        }
        $keptSteps = 0;
        if ($post !== null) {
            foreach ($post['images'] as $image) {
                if ((int)$image['Position'] > 1 && !in_array((int)$image['ImageID'], $v['remove'], true)) {
                    $keptSteps++;
                }
            }
        }
        if ($keptSteps + count($steps) > self::MAX_STEP_PHOTOS) {
            $errors['steps'] = 'A post can have at most ' . self::MAX_STEP_PHOTOS . ' step photos.';
        }
        return $errors;
    }

    // Open and store the pictures, then write the database. Returns errors (empty = saved, and then it redirects).
    private function save(PostModel $posts, ?array $post, array $v, ?array $challenge): array
    {
        $uploader = new ImageUploader(ROOT_PATH . '/uploads');
        $newFiles = [];                                // every picture file written during THIS request, to delete again if something fails
        $errors   = [];

        try {
            // ---- 1. the pictures (this is where the real checks happen) ----
            $cover = $_FILES['photo'] ?? null;
            $coverName = null;
            if (ImageUploader::chosen($cover)) {
                try {
                    $coverName = $uploader->store($cover);
                } catch (UploadException $e) {
                    throw new UploadException($e->getMessage(), 1);
                }
                $newFiles[] = $coverName;
            }

            $newSteps = [];
            foreach (ImageUploader::many($_FILES['steps'] ?? null) as $i => $file) {
                try {
                    $stored = $uploader->store($file);
                } catch (UploadException $e) {
                    throw new UploadException('Step photo ' . ($i + 1) . ': ' . $e->getMessage(), 2);
                }
                $newFiles[] = $stored;
                $newSteps[] = $stored;
            }

            // ---- 2. the final list of pictures, cover first ----
            $oldFiles = [];                            // files that are no longer used after this save
            $keptSteps = [];
            $oldCover = null;
            if ($post !== null) {
                foreach ($post['images'] as $image) {
                    if ((int)$image['Position'] === 1) {
                        $oldCover = $image['FileName'];
                    } elseif (in_array((int)$image['ImageID'], $v['remove'], true)) {
                        $oldFiles[] = $image['FileName'];
                    } else {
                        $keptSteps[] = $image['FileName'];
                    }
                }
            }
            if ($coverName !== null && $oldCover !== null) {
                $oldFiles[] = $oldCover;
            }
            $images = array_merge([$coverName ?? $oldCover], $keptSteps, $newSteps);

            // ---- 3. the database ----
            $data = [
                'title'       => $v['title'] !== '' ? $v['title'] : 'Untitled draft',
                'description' => $v['description'] !== '' ? $v['description'] : null,
                'alt'         => $v['alt'] !== '' ? $v['alt'] : null,
                'isDraft'     => $v['isDraft'],
                'humanMade'   => $v['humanMade'],
                // a running challenge is joined with the tick box; if none runs any more, an old link stays as it was
                'challengeId' => $challenge !== null
                    ? ($v['challenge'] ? (int)$challenge['ChallengeID'] : null)
                    : ($post['ChallengeID'] ?? null),
            ];

            // Make the one-use Back marker before saving, so a failure cannot happen after the database changed.
            $saveToken = bin2hex(random_bytes(16));
            if ($post === null) {
                $postId = $posts->create((int)$this->user['UserID'], $data, $v['categories'], $v['tags'], $images);
            } else {
                $postId = (int)$post['PostID'];
                $posts->update($postId, $data, $v['categories'], $v['tags'], $images);
                $uploader->deleteMany($oldFiles);      // only now: the database no longer points at them
            }
        } catch (UploadException $e) {
            $uploader->deleteMany($newFiles);
            return [($e->getCode() === 2 ? 'steps' : 'photo') => $e->getMessage()];
        } catch (Throwable $e) {
            $uploader->deleteMany($newFiles);
            error_log('Saving a post failed: ' . $e->getMessage());
            return ['form' => 'Something went wrong while saving. Nothing was posted. Please try again.'];
        }

        flash_set('success', $v['isDraft'] ? 'Draft saved. Only you can see it.' : 'Your post is live.');
        // The post page confirms this token before its JavaScript remembers which form was saved.
        $_SESSION['post_save_back'] = [
            'token'  => $saveToken,
            'postId' => $postId,
            'form'   => $post === null ? 'create' : 'edit-' . $postId,
        ];
        redirect_to(url('post', ['id' => $postId, 'saved' => $saveToken]));
        return $errors;                                // never reached: redirect_to() ends the script
    }

    // =================================================================
    //   Delete (Flow: "Delete post")
    //   owner or admin?  no  -> 403
    //   delete the database row first (pictures, likes and comments follow by CASCADE), THEN the picture files.
    //   That order means a failed step never leaves a post pointing at a missing picture.
    // =================================================================
    // This page keeps the "Are you sure?" step available without JavaScript.
    public function confirmDelete(): void
    {
        $post = $this->findFromQuery();
        if ($post === null || ($post['IsDraft'] && !$this->isOwner($post))) {
            $this->notFound();
            return;
        }
        if (!$this->isOwner($post) && !$this->isAdmin()) {
            http_response_code(403);
            $this->render('pages/forbidden', ['title' => 'No access']);
            return;
        }
        $this->render('post/delete', ['title' => 'Delete post', 'post' => $post]);
    }

    public function delete(): void
    {
        if (!is_post()) {
            redirect_to(url('home'));
        }
        if (!csrf_check()) {
            flash_set('error', 'The form expired. Please try again.');
            redirect_to(url('home'));
        }

        $posts = new PostModel($this->db);
        $id    = ctype_digit(post_str('id')) ? (int)post_str('id') : 0;
        $post  = $id > 0 ? $posts->find($id) : null;

        if ($post === null) {
            $this->notFound();
            return;
        }
        if (!$this->isOwner($post) && !$this->isAdmin()) {
            http_response_code(403);
            $this->render('pages/forbidden', ['title' => 'No access']);
            return;
        }

        $files = $posts->delete($id);
        (new ImageUploader(ROOT_PATH . '/uploads'))->deleteMany($files);

        flash_set('success', 'The post was deleted.');
        redirect_to(url($this->isOwner($post) ? 'profile' : 'home'));
    }

    // ---------------------------------------------------------------------
    // small helpers
    // ---------------------------------------------------------------------

    // ?id=7 -> the post, or null. The id is user input: only digits are accepted.
    private function findFromQuery(): ?array
    {
        $raw = $_GET['id'] ?? '';
        if (!is_string($raw) || !ctype_digit($raw) || $raw === '0') {
            return null;
        }
        return (new PostModel($this->db))->find((int)$raw, $this->viewerId());
    }

    // Return a message when this member has made too many new posts, otherwise null.
    private function postingLimitMessage(PostModel $posts): ?string
    {
        $userId = (int)$this->user['UserID'];

        if ($posts->countCreatedSince($userId, self::NEW_POSTS_SHORT_SECONDS) >= self::NEW_POSTS_SHORT) {
            return 'You are posting very fast. Please wait a few minutes and try again.';
        }
        if ($posts->countCreatedSince($userId, 86400) >= self::NEW_POSTS_DAY) {
            return 'You have reached the limit of ' . self::NEW_POSTS_DAY . ' new posts in 24 hours. Please come back tomorrow.';
        }
        return null;
    }

    private function isOwner(array $post): bool
    {
        return $this->user !== null && (int)$post['UserID'] === (int)$this->user['UserID'];
    }

    private function isAdmin(): bool
    {
        return $this->user !== null && $this->user['RoleName'] === 'admin';
    }
}
