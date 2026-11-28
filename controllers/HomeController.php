<?php
/*
HomeController  =  the front page.
Everybody may open it (see the route table in index.php). Visitors, members and admins see a different top part;
the view decides which, using $user.

The feed has tabs, a sort and pages. All three come from the address (?tab=hot&sort=newest&pg=2). The address is user input, so it is checked:
  tab   must be in a list we write ourselves (anything else silently becomes 'hot')
  sort  must be in a list we write ourselves (anything else silently becomes 'hot')
  pg    must be digits only, and at least 1 (anything else silently becomes 1)
  cat   the selected crafts of a member (?cat[]=3&cat[]=7). Only real craft ids are kept, everything else is ignored.
The tabs:
  hot        the Hot feed. The sort decides the order inside it:
               sort=hot     for a member: the posts in the crafts I picked first, then the rest. Inside each group the posts with the
                            most likes and comments lately come first (view hot_posts), then the newest.
                            For a visitor: the most liked and commented lately first.
               sort=newest  the newest posts first
  boards     empty for now (the boards will come later)
  following  the newest posts of the members I follow (members only: a visitor who asks for it gets the Hot feed)
The craft chips ("Your categories") sit on the Hot feed of a member. One chip for each craft the member picked on their profile.
Click a chip to tick it: the feed then shows ONLY posts that have one of the ticked crafts. Nothing ticked = the normal feed.
An old link with ?tab=newest still works: it opens the Hot feed with sort=newest.

Pages work with LIMIT and OFFSET: page 3 with 12 per page = skip 24 posts, take 12.
We ask for ONE MORE post than we show. If that extra post exists there is a next page, and we did not need a second
query that counts all posts.
Visitors only get a taste (the first 6 posts, no pages) and a prompt to join.
*/
class HomeController extends BaseController
{
    private const TABS             = ['hot', 'boards', 'following'];
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

        // Only members follow people, so a visitor cannot open the Following tab: they get the Hot feed.
        if ($tab === 'following' && $visitor) {
            $tab = 'hot';
        }

        $model = new PostModel($this->db);

        // The craft choices: only for a member, only on the Hot feed. Everyone else gets an empty list = no choices, no filter.
        // ! means "not": !$visitor reads "not a visitor", so a member.
        $chips     = [];
        $pickedIds = [];
        if ($tab === 'hot' && !$visitor) {
            $craftModel = new CategoryModel($this->db);
            $allCrafts  = $craftModel->all();
            $myCraftIds = $craftModel->idsForUser($this->viewerId());
            $pickedIds  = $this->readPickedCrafts($allCrafts);

            // One chip for each craft I picked on my profile. 'on' = the chip is ticked right now.
            foreach ($allCrafts as $craft) {
                $craftId = (int)$craft['CategoryID'];
                if (!in_array($craftId, $myCraftIds, true)) {
                    continue;    // not one of my crafts: no chip
                }
                $isOn = in_array($craftId, $pickedIds, true);

                // The ticked list AFTER a click on this chip: a ticked chip leaves the list, an unticked chip joins it.
                // !== means "not exactly equal".
                $afterClick = [];
                foreach ($pickedIds as $pickedId) {
                    if ($pickedId !== $craftId) {
                        $afterClick[] = $pickedId;
                    }
                }
                if (!$isOn) {
                    $afterClick[] = $craftId;
                }

                $chips[] = [
                    'name' => $craft['CategoryName'],
                    'on'   => $isOn,
                    'href' => $this->hotFeedUrl($sort, $afterClick),
                ];
            }
        }

        // Which posts? One list for each tab. The Boards tab has no posts yet, so its list stays empty.
        $rows = [];
        if ($tab === 'hot' && $sort === 'newest') {
            $rows = $model->latest($perPage + 1, $offset, $this->viewerId(), $pickedIds);
        } elseif ($tab === 'hot') {
            // true = the posts in the crafts I picked come first (a visitor has no crafts, so for a visitor nothing changes).
            $rows = $model->hot($perPage + 1, $offset, $this->viewerId(), true, $pickedIds);
        } elseif ($tab === 'following') {
            $rows = $model->following($this->viewerId(), $perPage + 1, $offset);
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
            'chips'     => $chips,
            'pickedIds' => $pickedIds,
            'clearUrl'  => $this->hotFeedUrl($sort, []),
            'pg'      => $pg,
            'coolest' => $model->coolest($this->viewerId()),
            'maker'   => $maker,
            // The newest comments under the maker's post fade in and out on the maker card.
            'makerComments' => $maker !== null ? (new CommentModel($this->db))->latestForPost((int)$maker['PostID'], 6) : [],
            // Only visitors need the five hottest posts for the hero pictures.
            'pins'    => $visitor ? $model->hot(5) : [],
        ]);
    }

    // The address of the Hot feed with the sort and with the given ticked craft ids. Used by the chips and by "Clear".
    private function hotFeedUrl(string $sort, array $craftIds): string
    {
        $query = ['tab' => 'hot'];
        if ($sort === 'newest') {
            $query['sort'] = 'newest';
        }
        if (count($craftIds) > 0) {
            $query['cat'] = $craftIds;
        }
        return url('home', $query) . '#feed';
    }

    // Read ?cat[]=... from the address. The address is user input, so we only keep ids that are REAL crafts.
    // $allCrafts = the rows from CategoryModel::all(). Anything that is not a real craft id is silently left out.
    private function readPickedCrafts(array $allCrafts): array
    {
        $fromAddress = $_GET['cat'] ?? [];      // ?? [] means: no cat in the address = an empty list
        if (!is_array($fromAddress)) {
            return [];
        }

        $realIds = [];
        foreach ($allCrafts as $craft) {
            $realIds[] = (int)$craft['CategoryID'];
        }

        $picked = [];
        foreach ($fromAddress as $value) {
            // Only text made of digits can be an id. || means "or" and ! means "not": skip it when it is not text, or not digits, or too long.
            // (int) turns the text "7" into the number 7.
            if (!is_string($value) || !ctype_digit($value) || strlen($value) > 4) {
                continue;
            }
            $craftId = (int)$value;
            if (in_array($craftId, $realIds, true)) {
                $picked[$craftId] = $craftId;     // using the id as the key removes doubles (same craft twice)
            }
        }
        return array_values($picked);
    }
}
