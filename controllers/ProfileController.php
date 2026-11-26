<?php
/*
ProfileController  =  the profile page of a member.

  show   one member's profile: name, badge, tagline, about text, crafts, numbers and their posts   (members only)
         index.php?page=profile                      my own profile
         index.php?page=profile&id=7                 the profile of member number 7
         index.php?page=profile&show=draft           my own posts, only the drafts (also: published, all)
  follow follow or unfollow a member   (members only, POST only: the button on the profile)
  edit   edit my own name, username, tagline, about text, badge and links   (members only)
         index.php?page=edit-profile

Who may see what. This is checked here in PHP, not just hidden in the view:
  - visitors cannot open the page at all (the route in index.php says 'member')
  - a member who is banned right now does not exist for other people (404 "not found")
  - a private profile shows only the name and "This profile is private" to other people (an admin may see it)
  - drafts are shown to the owner only (e-mail and birthdate are not on this page at all: they are on My account)
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

        // Step 7: the posts. Drafts only for the owner. A hidden profile shows no posts at all.
        $posts = [];
        if ($hidden === false) {
            $postModel     = new PostModel($this->db);
            $includeDrafts = $isOwn;
            $posts         = $postModel->forUser($profileId, $includeDrafts, $this->viewerId());
        }

        // Step 8: the number of published posts (drafts do not count, nobody else can see them).
        $publishedCount = 0;
        foreach ($posts as $post) {
            if ((int)$post['IsDraft'] === 0) {
                $publishedCount = $publishedCount + 1;
            }
        }

        // Step 9: on my own profile I can filter my posts: all, published or draft.
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

        // Step 10: crafts, links, the follower numbers and "do I follow this member?" (the model asks the database).
        $crafts      = [];
        $links       = [];
        $followers   = 0;
        $following   = 0;
        $isFollowing = false;
        if ($hidden === false) {
            $crafts    = $users->craftsOf($profileId);
            $links     = $users->linksOf($profileId);
            $followers = $users->countFollowers($profileId);
            $following = $users->countFollowing($profileId);
            // On my own profile there is no Follow button, so there is nothing to ask.
            if ($isOwn === false) {
                $isFollowing = $users->isFollowing($myId, $profileId);
            }
        }

        // Step 11: may the viewer see the number of followers? The owner can switch it off for other people.
        $showFollowers = true;
        if ((int)$member['ShowFollowerCount'] === 0 && $isOwn === false) {
            $showFollowers = false;
        }

        // Step 12: show the page.
        $this->render('profile/show', [
            'title'          => '@' . $member['Username'],
            'member'         => $member,
            'isOwn'          => $isOwn,
            'hidden'         => $hidden,
            'posts'          => $posts,
            'show'           => $show,
            'crafts'         => $crafts,
            'links'          => $links,
            'publishedCount' => $publishedCount,
            'followers'      => $followers,
            'following'      => $following,
            'showFollowers'  => $showFollowers,
            'isFollowing'    => $isFollowing,
        ]);
    }

    // =================================================================
    //   Follow or unfollow a member   (POST only: the Follow button on a profile)
    //   One button does both: if I follow the member already, it unfollows; if not, it follows.
    //   Who is following is always ME, from the login (the session). The form only says WHOM.
    // =================================================================
    public function follow(): void
    {
        $myId = (int)$this->user['UserID'];

        // Step 1: this address is only for the button. Typing it in the address bar goes back to my profile.
        if (!is_post()) {
            redirect_to(url('profile'));
        }

        // Step 2: whom? The id comes from the form. readId() gives 0 when it is not a plain number.
        $targetId = $this->readId(post_str('id'));

        // Where to go back to afterwards: the profile of that member (or mine, when the id was no number).
        $backUrl = url('profile');
        if ($targetId !== 0) {
            $backUrl = url('profile', ['id' => $targetId]);
        }

        // Step 3: is the form really from our own page? (CSRF token)
        if (!csrf_check()) {
            flash_set('error', 'The form expired. Please try again.');
            redirect_to($backUrl);
        }

        // Step 4: an id that is not a real number can never be a member.
        if ($targetId === 0) {
            $this->notFound();
            return;
        }

        // Step 5: nobody follows themselves (the database also refuses it, but a friendly message is better).
        if ($targetId === $myId) {
            flash_set('error', 'You cannot follow yourself.');
            redirect_to(url('profile'));
        }

        // Step 6: the member must exist.
        $users  = new UserModel($this->db);
        $member = $users->findProfile($targetId);
        if ($member === null) {
            $this->notFound();
            return;
        }

        // Step 7: a member who is banned right now does not exist for other people (same rule as the profile page).
        if (is_banned($member)) {
            $this->notFound();
            return;
        }

        // Step 8: a private profile cannot be followed by other people (admins may see it, so they may follow).
        $isAdmin = false;
        if ($this->user['RoleName'] === 'admin') {
            $isAdmin = true;
        }
        if ((int)$member['IsProfilePublic'] === 0 && $isAdmin === false) {
            $this->notFound();
            return;
        }

        // Step 9: follow or unfollow, depending on what is true in the database right now. Then say what happened.
        $alreadyFollowing = $users->isFollowing($myId, $targetId);
        if ($alreadyFollowing) {
            $users->unfollow($myId, $targetId);
            flash_set('success', 'You stopped following @' . $member['Username'] . '.');
        } else {
            $users->follow($myId, $targetId);
            flash_set('success', 'You now follow @' . $member['Username'] . '.');
        }

        // Post, Redirect, Get: go back to the profile (a refresh does not click the button again).
        redirect_to($backUrl);
    }

    // =================================================================
    //   Edit profile: the names, the tagline, the about text, the badge and the links   (my own profile only)
    //   GET   show the form with the texts I have now
    //   POST  check the form, check the lengths, save, then go back to my profile
    // There is no ?id= here on purpose: I can only ever edit MY OWN profile.
    // =================================================================
    public function edit(): void
    {
        // Step 1: whose profile? Always mine. The id comes from the login (the session), never from the address.
        $myId = (int)$this->user['UserID'];

        // Step 2: load my row (the model runs the SQL).
        $users  = new UserModel($this->db);
        $member = $users->findProfile($myId);
        if ($member === null) {
            $this->notFound();
            return;
        }

        // Step 3: the texts the form starts with. NULL in the database (not filled in) becomes an empty text.
        $old = ['tagline' => '', 'about' => '', 'badge' => ''];
        if ($member['Tagline'] !== null) {
            $old['tagline'] = $member['Tagline'];
        }
        if ($member['AboutText'] !== null) {
            $old['about'] = $member['AboutText'];
        }
        if ($member['Badge'] !== null) {
            $old['badge'] = $member['Badge'];
        }

        // The crafts: the fixed list of all crafts (the Category table) and the ids of the ones I have picked now.
        // CategoryModel is the same class that join step 2 uses.
        $categories    = new CategoryModel($this->db);
        $allCrafts     = $categories->all();
        $old['crafts'] = $categories->idsForUser($myId);

        // The names start with what is saved now (they can never be empty: sign-up needs them).
        $old['first_name'] = $member['FirstName'];
        $old['last_name']  = $member['LastName'];
        $old['username']   = $member['Username'];

        // The links: the form has 3 fields (link1, link2, link3). They start with the links I have saved.
        $linkFields = ['link1', 'link2', 'link3'];
        $savedLinks = $users->linksOf($myId);
        $position   = 0;
        foreach ($linkFields as $field) {
            $old[$field] = '';
            if (isset($savedLinks[$position])) {
                $old[$field] = $savedLinks[$position];
            }
            $position = $position + 1;
        }

        $errors        = [];
        $craftsProblem = '';    // stays empty when the crafts are fine; otherwise it holds the message

        // Step 4: the form was sent.
        if (is_post()) {
            // Is the whole form bigger than the server allows (post_max_size)? Then PHP throws ALL of it away, also the
            // CSRF token and the texts. So this is checked FIRST, and then the saved texts stay in the form.
            $tooBig = ImageUploader::requestTooBig();

            if ($tooBig === false) {
                // post_str() reads one text field from the form and removes spaces at the start and end.
                $old['tagline'] = post_str('tagline');
                $old['about']   = post_str('about');
                $old['badge']   = post_str('badge');
                $old['first_name'] = post_str('first_name');
                $old['last_name']  = post_str('last_name');
                $old['username']   = post_str('username');
                foreach ($linkFields as $field) {
                    $old[$field] = post_str($field);
                }

                // The ticked crafts. A real craft id is one of the ids in the Category table.
                $validIds = [];
                foreach ($allCrafts as $craft) {
                    $validIds[] = (int)$craft['CategoryID'];
                }
                $picked = $this->readPickedCrafts($validIds);
                if ($picked === null) {
                    // === null means "nothing came back": something in the list was not a real craft id.
                    $craftsProblem = 'Something in that list was not right. Please choose again.';
                } else {
                    $old['crafts'] = $picked;
                    if (count($picked) === 0) {
                        // The same rule as join step 2: a member always has at least one craft.
                        $craftsProblem = 'Please pick at least one craft.';
                    }
                }
            }

            // A browser sends a new line as 2 characters (\r\n) but counts it as 1 in maxlength.
            // We make it 1 as well, so a text the browser accepted is never refused here.
            $old['about'] = str_replace("\r\n", "\n", $old['about']);

            // Step 5: is the form really from our own page? (CSRF token, same idea as every other form.)
            if ($tooBig) {
                $errors['avatar'] = 'That file is too big to send. The limit is ' . ImageUploader::fileLimitText() . '. Nothing was saved.';
            } elseif (!csrf_check()) {
                $errors['form'] = 'The form expired. Please try again.';
            } else {
                // The names: the same rules as at sign-up. The rule functions live in includes/functions.php
                // and give back an empty text when everything is fine, or a message when it is not.
                $firstNameMessage = validate_person_name($old['first_name'], 'First name');
                if ($firstNameMessage !== '') {
                    $errors['first_name'] = $firstNameMessage;
                }
                $lastNameMessage = validate_person_name($old['last_name'], 'Last name');
                if ($lastNameMessage !== '') {
                    $errors['last_name'] = $lastNameMessage;
                }

                // The username is only checked when it was changed (so an old name can never block saving the rest).
                // !== means "is not exactly the same as".
                if ($old['username'] !== $member['Username']) {
                    $usernameMessage = validate_username($old['username']);
                    if ($usernameMessage !== '') {
                        $errors['username'] = $usernameMessage;
                    } elseif ($users->usernameTakenByOther($old['username'], $myId)) {
                        $errors['username'] = 'That username is taken.';
                    }
                }

                // Step 6: the lengths. The same limits as the database columns (80, 400 and 30 characters).
                // mb_strlen counts characters, also for letters like æ, ø and å.
                if (mb_strlen($old['tagline']) > 80) {
                    $errors['tagline'] = 'The tagline can be at most 80 characters.';
                }
                if (mb_strlen($old['about']) > 400) {
                    $errors['about'] = 'The text about you can be at most 400 characters.';
                }
                if (mb_strlen($old['badge']) > 30) {
                    $errors['badge'] = 'The badge can be at most 30 characters.';
                }

                // The crafts (read above): a message here means no real crafts or none at all.
                if ($craftsProblem !== '') {
                    $errors['crafts'] = $craftsProblem;
                }

                // The links. A field with nothing in it is skipped. A filled field must be a real web address.
                $newLinks = [];
                foreach ($linkFields as $field) {
                    if ($old[$field] !== '') {
                        // Add https:// when the member left it out (instagram.com/me becomes https://instagram.com/me).
                        $old[$field] = $this->addHttps($old[$field]);

                        if (mb_strlen($old[$field]) > 255) {
                            $errors[$field] = 'A link can be at most 255 characters.';
                        } elseif ($this->isWebAddress($old[$field]) === false) {
                            $errors[$field] = 'This does not look like a web address. Example: instagram.com/yourname';
                        } else {
                            $newLinks[] = $old[$field];     // good link: keep it for saving
                        }
                    }
                }

                // The photo: if a file was chosen, check it and save it (a square, 256 x 256). The member's wish
                // is only carried out when everything else in the form is fine, so no picture file is made for nothing.
                $newAvatar = null;
                $uploader  = new ImageUploader(ROOT_PATH . '/uploads');
                $chosen    = ImageUploader::chosen($_FILES['avatar'] ?? null);   // ?? = "or, when it is not there:"
                if (count($errors) === 0 && $chosen) {
                    try {
                        $newAvatar = $uploader->storeAvatar($_FILES['avatar']);
                    } catch (UploadException $ex) {
                        // The message is made for the member ("Only JPG, PNG and WEBP pictures are allowed.").
                        $errors['avatar'] = $ex->getMessage();
                    }
                }

                // Step 7: no errors? Then save. An empty text is saved as NULL (= "not filled in").
                if (count($errors) === 0) {
                    $tagline = null;
                    if ($old['tagline'] !== '') {
                        $tagline = $old['tagline'];
                    }
                    $about = null;
                    if ($old['about'] !== '') {
                        $about = $old['about'];
                    }
                    $badge = null;
                    if ($old['badge'] !== '') {
                        $badge = $old['badge'];
                    }

                    // A transaction = all or nothing: the names, the texts and the links are saved together, or none of them.
                    $this->db->beginTransaction();
                    $users->updateNames($myId, $old['first_name'], $old['last_name'], $old['username']);
                    $users->updateProfileTexts($myId, $tagline, $about, $badge);
                    $users->replaceLinks($myId, $newLinks);

                    // The photo: a new file replaces the old one. The ticked box "Remove my photo" (only when no new file
                    // was chosen) sets the photo back to nothing. Otherwise the saved photo stays as it is.
                    $oldAvatar = $member['AvatarFile'];   // the file name saved now (or NULL), loaded at the top of this method
                    $removeBox = post_str('remove_avatar');
                    if ($newAvatar !== null) {
                        $users->updateAvatar($myId, $newAvatar);
                    } elseif ($removeBox === '1') {
                        $users->updateAvatar($myId, null);
                    }
                    $this->db->commit();

                    // The crafts: the same function as in join step 2. It has its own transaction, so it runs after the commit above.
                    $categories->replaceForUser($myId, $old['crafts']);

                    // The database is saved. NOW the old picture file can go (before this, a failure would have lost it).
                    // Did the photo change? Yes when a new one was saved, or when the remove box was ticked.
                    $photoChanged = false;
                    if ($newAvatar !== null) {
                        $photoChanged = true;
                    }
                    if ($removeBox === '1') {
                        $photoChanged = true;
                    }
                    // && means "and": there has to be an old file AND the photo has to have changed.
                    if ($oldAvatar !== null && $photoChanged === true) {
                        $uploader->delete($oldAvatar);
                    }

                    // Post, Redirect, Get: show a message once and go back to the profile (a refresh does not send the form again).
                    flash_set('success', 'Profile saved.');
                    redirect_to(url('profile'));
                }
            }
        }

        // Step 8: show the form (first visit, or again with the error messages and what was typed).
        $this->render('profile/edit', [
            'title'  => 'Edit profile',
            'old'    => $old,
            'errors' => $errors,
            'allCrafts' => $allCrafts,
        ]);
    }

    // Reads the crafts the member ticked in the form (the list crafts[]).
    // Gives back a list of craft ids, or null when something in the list is not a real craft id.
    // ?array in the first line means "an array, or null".
    private function readPickedCrafts(array $validIds): ?array
    {
        // ?? = "or, when it is not there:". No box ticked means there is no crafts[] at all, so we start with an empty list.
        $posted = $_POST['crafts'] ?? [];
        if (!is_array($posted)) {
            return null;
        }
        $picked = [];
        foreach ($posted as $value) {
            // A real id is a text with only digits in it ...
            if (!is_string($value)) {
                return null;
            }
            if (!ctype_digit($value)) {
                return null;
            }
            // ... and it must be one of the ids in the Category table.
            $id = (int)$value;
            if (in_array($id, $validIds, true) === false) {
                return null;
            }
            // The id is also the key, so a craft that is sent twice counts only once.
            $picked[$id] = $id;
        }
        return array_values($picked);
    }

    // Puts https:// in front of a web address when the member left it out (instagram.com/me becomes https://instagram.com/me).
    // If the text already has "://" in it, it is left alone: it either starts with http:// or https:// (fine),
    // or it is another kind of address like ftp:// (isWebAddress() refuses that one).
    private function addHttps(string $text): string
    {
        $hasScheme = str_contains($text, '://');
        if ($hasScheme === false) {
            return 'https://' . $text;
        }
        return $text;
    }

    // True when the text is a real web address: it starts with http:// or https:// AND filter_var accepts the rest.
    // filter_var() with FILTER_VALIDATE_URL checks the text against the rules for a web address and gives false if it breaks them.
    private function isWebAddress(string $url): bool
    {
        $lower    = strtolower($url);       // so HTTPS:// and https:// count the same
        $hasHttp  = str_starts_with($lower, 'http://');
        $hasHttps = str_starts_with($lower, 'https://');
        if ($hasHttp === false && $hasHttps === false) {
            return false;                   // another kind of address, for example ftp://
        }
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        return true;
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
