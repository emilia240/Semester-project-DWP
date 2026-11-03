<?php
/*
OnboardingController handles join step 2 (crafts) and step 3 (guidelines).
Only confirmed, logged-in members can open these pages.
*/
class OnboardingController extends BaseController
{
    // Pick at least one craft, or change the crafts saved earlier.
    public function crafts(): void
    {
        $categories = new CategoryModel($this->db);
        $all = $categories->all();
        $userId = (int)$this->user['UserID'];
        $errors = [];
        $chosen = $categories->idsForUser($userId);

        if (is_post()) {
            if (!csrf_check()) {
                $errors['form'] = 'The form expired. Please try again.';
            } else {
                // Check every submitted id against the actual categories, not just the browser's boxes.
                $validIds = array_map('intval', array_column($all, 'CategoryID'));
                $posted = $_POST['crafts'] ?? [];
                $picked = [];
                $bad = !is_array($posted);

                if (!$bad) {
                    foreach ($posted as $value) {
                        if (is_string($value) && ctype_digit($value) && in_array((int)$value, $validIds, true)) {
                            $picked[(int)$value] = (int)$value; // duplicate boxes count only once
                        } else {
                            $bad = true;
                        }
                    }
                }
                $picked = array_values($picked);

                if ($bad) {
                    $errors['crafts'] = 'Something in that list was not right. Please choose again.';
                } elseif (!$picked) {
                    $errors['crafts'] = 'Please pick at least one craft.';
                }

                if (!$errors) {
                    try {
                        $categories->replaceForUser($userId, $picked);
                        $next = onboarding_next_step($this->db, $this->user);
                        redirect_to(url($next ?? 'members'));
                    } catch (PDOException $ex) {
                        error_log('saving crafts failed: ' . $ex->getMessage());
                        $errors['form'] = 'Something went wrong on our side. Please try again later.';
                    }
                } else {
                    $chosen = $picked;
                }
            }
        }

        $this->render('onboarding/crafts', [
            'layout'       => 'split',
            'title'        => 'Join: your crafts',
            'errors'       => $errors,
            'categories'   => $all,
            'chosen'       => $chosen,
            'inOnboarding' => onboarding_next_step($this->db, $this->user) !== null,
        ]);
    }

    // Read and accept the guidelines, but only after choosing a craft.
    public function guidelines(): void
    {
        $next = onboarding_next_step($this->db, $this->user);
        if ($next === 'join-crafts') {
            redirect_to(url('join-crafts'));
        }
        if ($next === null) {
            redirect_to(url('home'));
        }

        $errors = [];
        $rules = (new SiteSettingModel($this->db))->rules();
        $cards = $rules === null ? [] : $this->ruleCards($rules);
        if (!$cards) {
            $rules = null;
            error_log('join guidelines: no usable rule cards');
            $errors['form'] = 'The guidelines are not available right now. Please try again later.';
        }

        if (is_post()) {
            if (!csrf_check()) {
                $errors['form'] = 'The form expired. Please try again.';
            } elseif (!isset($_POST['agree'])) {
                $errors['agree'] = 'Please tick the box to join.';
            } elseif ($rules !== null) {
                (new UserModel($this->db))->acceptGuidelines((int)$this->user['UserID']);
                flash_set('success', 'Welcome to To Be Human, ' . $this->user['Username'] . '! You are all set.');
                redirect_to(url('home'));
            }
        }

        $this->render('onboarding/guidelines', [
            'layout' => 'split',
            'title'  => 'Join: the guidelines',
            'errors' => $errors,
            'rules'  => $rules,
            'cards'  => $cards,
        ]);
    }

    // The rules are stored as one text: each blank line starts another card.
    // The first line is the title, and the remaining lines are its explanation.
    private function ruleCards(string $text): array
    {
        // Normalise Windows and older line endings before looking for blank lines.
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        $cards = [];
        foreach (preg_split('/\n[ \t]*\n+/', $text) as $block) {
            $lines = explode("\n", trim($block), 2);
            $title = trim($lines[0]);
            if ($title !== '') {
                $cards[] = [$title, trim($lines[1] ?? '')];
            }
        }
        return $cards;
    }
}
