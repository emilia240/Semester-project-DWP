<?php
/*
HomeController  =  the front page.
Everybody may open it (see the route table in index.php). Visitors, members and admins see a different top part;
the view decides which, using $user.

The feed has tabs and pages. Both come from the address (?tab=hot&pg=2). The address is user input, so it is checked:
  tab   must be in a list we write ourselves (anything else silently becomes 'hot')
  pg    must be digits only, and at least 1 (anything else silently becomes 1)
The tabs:
  hot     the posts with the most likes and comments lately first (view hot_posts), then the rest, newest first
  newest  the newest posts first

Pages work with LIMIT and OFFSET: page 3 with 12 per page = skip 24 posts, take 12.
We ask for ONE MORE post than we show. If that extra post exists there is a next page, and we did not need a second
query that counts all posts.
Visitors only get a taste (the first 6 posts, no pages) and a prompt to join.
*/
class HomeController extends BaseController
{
    private const TABS             = ['hot', 'newest'];
    private const PER_PAGE_MEMBER  = 12;
    private const PER_PAGE_VISITOR = 6;
    private const MAX_PAGE         = 9999;    // Limits the OFFSET used by a page number from the address.

    public function index(): void
    {
        $tab = $_GET['tab'] ?? 'hot';
        if (!is_string($tab) || !in_array($tab, self::TABS, true)) {
            $tab = 'hot';
        }

        $pg = $_GET['pg'] ?? '1';
        $pg = (is_string($pg) && ctype_digit($pg) && $pg !== '' && strlen($pg) <= 4) ? min(max((int)$pg, 1), self::MAX_PAGE) : 1;

        $visitor = $this->user === null;
        $perPage = $visitor ? self::PER_PAGE_VISITOR : self::PER_PAGE_MEMBER;
        $pg      = $visitor ? 1 : $pg;
        $offset  = ($pg - 1) * $perPage;

        $model = new PostModel($this->db);
        $rows  = $tab === 'newest'
            ? $model->latest($perPage + 1, $offset, $this->viewerId())
            : $model->hot($perPage + 1, $offset, $this->viewerId());
        $hasNext = count($rows) > $perPage;

        $this->render('home', [
            'title'   => SITE_NAME,
            'posts'   => array_slice($rows, 0, $perPage),
            'hasNext' => $hasNext,
            // Do not offer a Next link that would lead back to the same maximum page.
            'atPageLimit' => !$visitor && $pg === self::MAX_PAGE && $hasNext,
            'tab'     => $tab,
            'pg'      => $pg,
            'coolest' => $model->coolest($this->viewerId()),
            'maker'   => $model->makerOfTheWeek(),
        ]);
    }
}
