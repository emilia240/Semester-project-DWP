<?php
/*
ExploreController  =  the Explore page: a wall of posts to browse.
It shows the newest posts of everybody, with pages. On top there is a chip for EVERY craft: tick one or more and the wall shows only
posts that have one of the ticked crafts. Nothing ticked = all posts. (The search bar comes in the next step.)
Everybody may open it (see the route table in index.php). Like the front page, a visitor only gets a taste
(the first 6 posts, no pages) and a prompt to join. A member gets 12 posts on a page.

The page number comes from the address (?pg=2). The address is user input, so it is checked:
  pg    must be digits only, and at least 1 (anything else silently becomes 1)
  cat   the ticked craft chips (?cat[]=3&cat[]=7). Only real craft ids are kept, everything else is ignored.

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

        // The craft chips: one for EVERY craft (visitors too). The ticked ones come from the address.
        $craftModel = new CategoryModel($this->db);
        $allCrafts  = $craftModel->all();
        $pickedIds  = $this->readPickedCrafts($allCrafts);

        $chips = [];
        foreach ($allCrafts as $craft) {
            $craftId = (int)$craft['CategoryID'];
            $isOn    = in_array($craftId, $pickedIds, true);

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
                'href' => $this->exploreUrl($afterClick),
            ];
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
        // $pickedIds = the ticked crafts. An empty list means: no filter, all posts.
        $rows    = $model->latest($perPage + 1, $offset, $this->viewerId(), $pickedIds);
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
            'chips'       => $chips,
            'pickedIds'   => $pickedIds,
            'clearUrl'    => $this->exploreUrl([]),
        ]);
    }

    // The address of the Explore page with the given ticked craft ids. Used by the chips and by "Clear".
    // #explore-crafts is a jump point: after a click the browser scrolls to the chips, so you do not lose your place.
    private function exploreUrl(array $craftIds): string
    {
        $query = [];
        if (count($craftIds) > 0) {
            $query['cat'] = $craftIds;
        }
        return url('explore', $query) . '#explore-crafts';
    }

    // Read ?cat[]=... from the address. The address is user input, so we only keep ids that are REAL crafts.
    // $allCrafts = the rows from CategoryModel::all(). Anything that is not a real craft id is silently left out.
    // (HomeController has the same function for the chips on the front page.)
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
