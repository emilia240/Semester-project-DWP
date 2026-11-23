<?php
/*
HomeController  =  the front page.
Everybody may open it (see the route table in index.php). Visitors, members and admins see a different top part;
the view decides which, using $user. Below it: the newest published posts (drafts never show up here).
*/
class HomeController extends BaseController
{
    public function index(): void
    {
        $posts = (new PostModel($this->db))->latest($this->user === null ? 6 : 24, 0, $this->viewerId());
        $this->render('home', ['title' => SITE_NAME, 'posts' => $posts]);
    }
}
