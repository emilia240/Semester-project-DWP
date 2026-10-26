<?php
/*
members.php  =  a page ONLY for logged-in members. Template for every future protected page:
   1. require init.php
   2. $user = confirm_logged_in();     <- if not allowed, the visitor never gets past this line
*/
require_once 'includes/init.php';

$user = confirm_logged_in();

$pageTitle = 'Members';
require 'includes/header.php';
?>

<h1>Members only</h1>
<p>Hello <?= e($user['FirstName'] . ' ' . $user['LastName']) ?> - you got in because you are logged in.</p>

<table>
    <tr><th>Username</th><td><?= e($user['Username']) ?></td></tr>
    <tr><th>E-mail</th><td><?= e($user['Email']) ?></td></tr>
    <tr><th>Role</th><td><?= e($user['RoleName']) ?></td></tr>
</table>

<?php require 'includes/footer.php'; ?>
