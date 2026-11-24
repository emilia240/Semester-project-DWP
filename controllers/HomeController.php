<?php
/*
HomeController  =  the front page.
Everybody may open it (see the route table in index.php). Visitors, members and admins see a different top part;
the view decides which, using $user.

The feed has tabs. Which one is shown comes from the address (?tab=hot or ?tab=newest). The address is user input,
so it is checked against a list we write ourselves, and anything else silently becomes the default.
  hot     the posts with the most likes and comments lately first (view hot_posts), then the rest, newest first
  newest  the newest posts first
*/
class HomeController extends BaseController
{
    private const TABS = ['hot', 'newest'];

    public function index(): void
    {
        $tab = $_GET['tab'] ?? 'hot';
        if (!is_string($tab) || !in_array($tab, self::TABS, true)) {
            $tab = 'hot';
        }

        $model = new PostModel($this->db);
        $limit = $this->user === null ? 6 : 24;
        $posts = $tab === 'newest'
            ? $model->latest($limit, 0, $this->viewerId())
            : $model->hot($limit, 0, $this->viewerId());

        $this->render('home', [
            'title'   => SITE_NAME,
            'posts'   => $posts,
            'tab'     => $tab,
            'coolest' => $model->coolest($this->viewerId()),
        ]);
    }
}
