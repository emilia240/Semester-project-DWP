<?php
/*
HomeController  =  the front page.
Everybody may open it (see the route table in index.php). Visitors, members and admins see a different top part;
the view decides which, using $user.

The feed has tabs, a sort and pages. All three come from the address (?tab=hot&sort=newest&pg=2). The address is user input, so it is checked:
  tab   must be in a list we write ourselves (anything else silently becomes 'hot')
  sort  must be in a list we write ourselves (anything else silently becomes 'hot')
  pg    must be digits only, and at least 1 (anything else silently becomes 1)
The tab: only the Hot feed for now (the tabs Boards and Following come in the next step). The sort decides the order inside it:
  sort=hot     the posts with the most likes and comments lately first (view hot_posts), then the rest, newest first
  sort=newest  the newest posts first
An old link with ?tab=newest still works: it opens the Hot feed with sort=newest.

Pages work with LIMIT and OFFSET: page 3 with 12 per page = skip 24 posts, take 12.
We ask for ONE MORE post than we show. If that extra post exists there is a next page, and we did not need a second
query that counts all posts.
Visitors only get a taste (the first 6 posts, no pages) and a prompt to join.
*/
class HomeController extends BaseController
{
    private const TABS             = ['hot'];
    private const SORTS            = ['hot', 'newest'];
    private const PER_PAGE_MEMBER  = 12;
    private const PER_PAGE_VISITOR = 6;
    private const MAX_PAGE         = 9999;    // Limits the OFFSET used by a page number from the address.

    public function index(): void
    {
        // The sort starts as 'hot'. It only matters inside the Hot feed.
        $sort = 'hot';

        $tab = $_GET['tab'] ?? 'hot';
        // === means "exactly equal". An old link with ?tab=newest becomes: Hot feed, sorted by newest.
        if ($tab === 'newest') {
            $tab  = 'hot';
            $sort = 'newest';
        }
        if (!is_string($tab) || !in_array($tab, self::TABS, true)) {
            $tab = 'hot';
        }

        // ?sort=... from the address: only 'hot' or 'newest' is accepted. Anything else is ignored.
        // ?? '' means: when there is no sort in the address, use an empty text. && means "and also".
        $sortFromAddress = $_GET['sort'] ?? '';
        if (is_string($sortFromAddress) && in_array($sortFromAddress, self::SORTS, true)) {
            $sort = $sortFromAddress;
        }

        $pg = $_GET['pg'] ?? '1';
        $pg = (is_string($pg) && ctype_digit($pg) && $pg !== '' && strlen($pg) <= 4) ? min(max((int)$pg, 1), self::MAX_PAGE) : 1;

        $visitor = $this->user === null;
        $perPage = $visitor ? self::PER_PAGE_VISITOR : self::PER_PAGE_MEMBER;
        $pg      = $visitor ? 1 : $pg;
        $offset  = ($pg - 1) * $perPage;

        $model = new PostModel($this->db);

        // Which posts? The sort decides: newest first, or the hot order.
        if ($sort === 'newest') {
            $rows = $model->latest($perPage + 1, $offset, $this->viewerId());
        } else {
            $rows = $model->hot($perPage + 1, $offset, $this->viewerId());
        }
        $hasNext = count($rows) > $perPage;

        $maker = $model->makerOfTheWeek();

        $this->render('home', [
            'title'   => SITE_NAME,
            'posts'   => array_slice($rows, 0, $perPage),
            'hasNext' => $hasNext,
            // Do not offer a Next link that would lead back to the same maximum page.
            'atPageLimit' => !$visitor && $pg === self::MAX_PAGE && $hasNext,
            'tab'     => $tab,
            'sort'    => $sort,
            'pg'      => $pg,
            'coolest' => $model->coolest($this->viewerId()),
            'maker'   => $maker,
            // The newest comments under the maker's post fade in and out on the maker card.
            'makerComments' => $maker !== null ? (new CommentModel($this->db))->latestForPost((int)$maker['PostID'], 6) : [],
            // Only visitors need the five hottest posts for the hero pictures.
            'pins'    => $visitor ? $model->hot(5) : [],
        ]);
    }
}
