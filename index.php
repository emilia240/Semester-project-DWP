<?php
/*
index.php  =  the front page. Everybody may see it.
*/
require_once 'includes/init.php';

$pageTitle = 'Home';
require 'includes/header.php';
?>

<h1>Welcome to <?= e(SITE_NAME) ?></h1>
<p>A place to share things you made by hand.</p>

<?php require 'includes/footer.php'; ?>
