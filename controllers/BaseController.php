<?php
/*
BaseController  =  what every controller has in common.

A CONTROLLER is a "heavy lifter": it reads what the visitor sent (the form), validates it, asks the models
to read or write the database, and finally chooses a VIEW to show or a page to redirect to.
It does not contain SQL (that is the model's job) and no HTML (that is the view's job).
This is "separation of concerns": every kind of work lives in one place.

"abstract" = this class is only a template. You cannot write  new BaseController(...)  yourself,
you make a child class:  class HomeController extends BaseController { ... }
The child automatically gets $this->db, $this->user, $this->page and $this->render().
*/
abstract class BaseController
{
    protected PDO $db;           // the database connection (made once in includes/connection.php)
    protected ?array $user;      // the logged-in user, or null for a visitor (index.php already checked the access rules)
    protected string $page;      // the page name from ?page=..., used to highlight the current menu item

    // index.php creates the controller:  new HomeController($db, $user, 'home')
    public function __construct(PDO $db, ?array $user, string $page)
    {
        $this->db   = $db;
        $this->user = $user;
        $this->page = $page;
    }

    // Show a view inside the layout. The user and page name are handed to the view automatically.
    protected function render(string $view, array $vars = []): void
    {
        render($view, $vars + ['user' => $this->user, 'page' => $this->page]);
    }

    // Show a 404 page (page not found) and stop.
    protected function notFound(): void
    {
        http_response_code(404);
        $this->render('pages/not_found', ['title' => 'Page not found']);
    }
}
