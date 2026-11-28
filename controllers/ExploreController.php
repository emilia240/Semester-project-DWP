<?php
/*
ExploreController  =  the Explore page: a wall of posts to browse.
Step 1 shows the newest posts of everybody, with pages. (The craft filter and the search bar come in the next steps.)
Everybody may open it (see the route table in index.php). Like the front page, a visitor only gets a taste
(the first 6 posts, no pages) and a prompt to join. A member gets 12 posts on a page.

The page number comes from the address (?pg=2). The address is user input, so it is checked:
  pg    must be digits only, and at least 1 (anything else silently becomes 1)

Pages work with LIMIT and OFFSET: page 3 with 12 per page = skip 24 posts, take 12.
We ask for ONE MORE post than we show. If that extra post exists there is a next page, and we did not need a second
query that counts all posts.
*/
class ExploreController extends BaseController
{
    private const PER_PAGE_MEMBER  = 12;
    private const PER_PAGE_VISITOR = 6;
    private const MAX_PAGE         = 9999;    // Limits the OFFSET used by a page number from the address.

    public function index(): void
    {
        // Read the page number. It starts as 1 and only changes when the address holds a good number.
        $pg = 1;
        // ?? '1' means: when there is no pg in the address, use the text '1'.
        $pgFromAddress = $_GET['pg'] ?? '1';
        // && means "and also". ctype_digit is true when the text has digits only. A text longer than 4 characters is too big.
        if (is_string($pgFromAddress) && ctype_digit($pgFromAddress) && strlen($pgFromAddress) <= 4) {
            // (int) turns the text "3" into the number 3.
            $pg = (int)$pgFromAddress;
        }
        if ($pg < 1) {
            $pg = 1;
        }
        if ($pg > self::MAX_PAGE) {
            $pg = self::MAX_PAGE;
        }

        // A visitor only gets the first 6 posts. A member gets 12 posts on every page.
        $visitor = $this->user === null;
        $perPage = self::PER_PAGE_MEMBER;
        if ($visitor) {
            $perPage = self::PER_PAGE_VISITOR;
            $pg      = 1;
        }
        $offset = ($pg - 1) * $perPage;

        // The newest published posts. The model already hides drafts and posts of banned members.
        $model   = new PostModel($this->db);
        $rows    = $model->latest($perPage + 1, $offset, $this->viewerId());
        $hasNext = count($rows) > $perPage;
        // array_slice keeps only the posts we show. The extra post (only used to find out about the next page) is dropped.
        $posts   = array_slice($rows, 0, $perPage);

        // Do not offer a Next link that would lead back to the same maximum page.
        $atPageLimit = false;
        if (!$visitor && $pg === self::MAX_PAGE && $hasNext) {
            $atPageLimit = true;
        }

        $this->render('explore', [
            'title'       => 'Explore',
            'posts'       => $posts,
            'hasNext'     => $hasNext,
            'atPageLimit' => $atPageLimit,
            'pg'          => $pg,
        ]);
    }
}
