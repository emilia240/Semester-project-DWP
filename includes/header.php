<?php
/*
header.php  =  the top of every page (menu + messages).
A page sets $pageTitle first, then requires this file.
Very little styling on purpose - design comes later.
*/
$navUser = logged_in() ? current_user() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($pageTitle ?? 'Welcome') . ' - ' . SITE_NAME) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="top">
    <a class="brand" href="index.php"><?= e(SITE_NAME) ?></a>
    <nav>
        <a href="index.php">Home</a>
        <?php if ($navUser): ?>
            <a href="members.php">Members</a>
            <!-- Logout is a small POST form with a CSRF token, so another website cannot log you out by sneaking in a link. -->
            <form class="inline" method="post" action="logout.php">
                <?= csrf_field() ?>
                <button type="submit" class="linklike">Log out (<?= e($navUser['Username']) ?>)</button>
            </form>
        <?php else: ?>
            <a href="login.php">Log in</a>
            <a href="signup.php">Sign up</a>
        <?php endif; ?>
    </nav>
</header>

<main>
<?php foreach (flash_get_all() as $f): ?>
    <p class="msg msg-<?= e($f['type']) ?>"><?= e($f['message']) ?></p>
<?php endforeach; ?>
