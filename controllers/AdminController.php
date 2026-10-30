<?php
/*
AdminController  =  PLACEHOLDER for the admin area (teacher requirement).
Only users with the role "admin" get here: index.php checks the role BEFORE this class is even created
(route access 'admin'). The real admin tools (ban, delete ...) come later.
*/
class AdminController extends BaseController
{
    public function index(): void
    {
        $this->render('admin/index', ['title' => 'Admin area']);
    }
}
