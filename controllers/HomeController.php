<?php
/*
HomeController  =  the front page.
Everybody may open it (see the route table in index.php). Visitors, members and admins see a different top part;
the view decides which, using $user.
*/
class HomeController extends BaseController
{
    public function index(): void
    {
        $this->render('home', ['title' => SITE_NAME]);
    }
}
