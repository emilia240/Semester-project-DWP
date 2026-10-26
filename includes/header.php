<?php
/*
header.php  =  the top of every page (menu + messages).
A page sets $pageTitle first, then requires this file.
Very little styling on purpose - design comes later.
*/
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
        <a href="signup.php">Sign up</a>
    </nav>
</header>

<main>
<?php foreach (flash_get_all() as $f): ?>
    <p class="msg msg-<?= e($f['type']) ?>"><?= e($f['message']) ?></p>
<?php endforeach; ?>
