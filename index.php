<?php
/*
index.php  =  the front page. Everybody may see it.
Logged in  -> "Hello, (name)"
Logged out -> short welcome + links
*/
require_once 'includes/init.php';

$user = logged_in() ? current_user() : null;

$pageTitle = 'Home';
require 'includes/header.php';
?>

<?php if ($user): ?>
    <h1>Hello, <?= e($user['FirstName']) ?>!</h1>
    <p>You are logged in as <strong><?= e($user['Username']) ?></strong>.</p>
    <p><a href="members.php">Go to the members page</a></p>
<?php else: ?>
    <h1>Welcome to <?= e(SITE_NAME) ?></h1>
    <p>A place to share things you made by hand.</p>
    <p><a href="signup.php">Create an account</a> or <a href="login.php">log in</a>.</p>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
