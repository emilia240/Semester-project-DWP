<?php
/*
ProfileController  =  the profile page of a member.

  show   one member's profile: name, badge, tagline, about text, crafts, numbers and their posts   (members only)
         index.php?page=profile                      my own profile
         index.php?page=profile&id=7                 the profile of member number 7
         index.php?page=profile&show=draft           my own posts, only the drafts (also: published, all)

Who may see what. This is checked here in PHP, not just hidden in the view:
  - visitors cannot open the page at all (the route in index.php says 'member')
  - a member who is banned right now does not exist for other people (404 "not found")
  - a private profile shows only the name and "This profile is private" to other people (an admin may see it)
  - e-mail, birthdate and drafts are shown to the owner only
*/
class ProfileController extends BaseController
{
    public function show(): void
    {
        // Step 1: whose profile is it? No id in the address means my own profile.
        $myId      = (int)$this->user['UserID'];
        $profileId = $myId;
        if (isset($_GET['id'])) {
            $profileId = $this->readId($_GET['id']);
        }

        // Step 2: an id that is not a real number can never be a member.
        if ($profileId === 0) {
            $this->notFound();
            return;
        }

        // Step 3: ask the model for the member (it runs the SQL). null means there is no such member.
        $users  = new UserModel($this->db); //instantiate the user model to interact with the users table
        $member = $users->findProfile($profileId);
        if ($member === null) {
            $this->notFound();
            return;
        }

        // Step 4: who is looking? Me, or someone else?
        $isOwn = false;
        if ($profileId === $myId) {
            $isOwn = true;
        }

        // Step 5: a member who is banned right now is not shown to other people.
        // is_banned() is a small helper in includes/functions.php (permanent ban, or a ban date in the future).
        if ($isOwn === false && is_banned($member)) {
            $this->notFound();
            return;
        }

        // Step 6: is the profile hidden from this viewer? Private profiles are hidden from everybody except the owner and admins.
        $isAdmin = false;
        if ($this->user['RoleName'] === 'admin') {
            $isAdmin = true;
        }
        $hidden = false;
        if ((int)$member['IsProfilePublic'] === 0 && $isOwn === false && $isAdmin === false) {
            $hidden = true;
        }

        // Step 7: e-mail and birthdate are private data. Remove them from the row unless it is my own profile,
        // so that the view cannot show them by mistake.
        if ($isOwn === false) {
            unset($member['Email']);
            unset($member['Birthdate']);
        }

        // Step 8: the posts. Drafts only for the owner. A hidden profile shows no posts at all.
        $posts = [];
        if ($hidden === false) {
            $postModel     = new PostModel($this->db);
            $includeDrafts = $isOwn;
            $posts         = $postModel->forUser($profileId, $includeDrafts, $this->viewerId());
        }

        // Step 9: the number of published posts (drafts do not count, nobody else can see them).
        $publishedCount = 0;
        foreach ($posts as $post) {
            if ((int)$post['IsDraft'] === 0) {
                $publishedCount = $publishedCount + 1;
            }
        }

        // Step 10: on my own profile I can filter my posts: all, published or draft.
        // Only these three words are allowed, anything else means "all".
        $show = 'all';
        if (isset($_GET['show']) && $isOwn) {
            if ($_GET['show'] === 'published' || $_GET['show'] === 'draft') {
                $show = $_GET['show'];
            }
        }
        if ($show !== 'all') {
            $kept = [];
            foreach ($posts as $post) {
                $isDraft = ((int)$post['IsDraft'] === 1);
                if ($show === 'draft' && $isDraft) {
                    $kept[] = $post;
                }
                if ($show === 'published' && $isDraft === false) {
                    $kept[] = $post;
                }
            }
            $posts = $kept;
        }

        // Step 11: crafts and the follower numbers (the model counts them in the database).
        $crafts    = [];
        $followers = 0;
        $following = 0;
        if ($hidden === false) {
            $crafts    = $users->craftsOf($profileId);
            $followers = $users->countFollowers($profileId);
            $following = $users->countFollowing($profileId);
        }

        // Step 12: may the viewer see the number of followers? The owner can switch it off for other people.
        $showFollowers = true;
        if ((int)$member['ShowFollowerCount'] === 0 && $isOwn === false) {
            $showFollowers = false;
        }

        // Step 13: show the page.
        $this->render('profile/show', [
            'title'          => '@' . $member['Username'],
            'member'         => $member,
            'isOwn'          => $isOwn,
            'hidden'         => $hidden,
            'posts'          => $posts,
            'show'           => $show,
            'crafts'         => $crafts,
            'publishedCount' => $publishedCount,
            'followers'      => $followers,
            'following'      => $following,
            'showFollowers'  => $showFollowers,
        ]);
    }

    // The id from the address as a whole number, or 0 when it is not a plain number.
    // Anybody can type anything after ?id= so we check it (not "abc", not "-1", not id[]=1).
    private function readId($raw): int
    {
        if (!is_string($raw)) {
            return 0;
        }
        if (!ctype_digit($raw)) {
            return 0;
        }
        if (strlen($raw) > 10) {
            return 0;
        }
        return (int)$raw;
    }
}
