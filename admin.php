<?php
/*
admin.php  =  PLACEHOLDER for the admin area (teacher requirement). Only users with the role "admin".
It shows how the role check works. The real admin tools (ban, delete ...) come later.
*/
require_once 'includes/init.php';

$user = confirm_admin();     // 403 for members, login page for visitors

$pageTitle = 'Admin';
require 'includes/header.php';
?>

<h1>Admin area</h1>
<p>Hello <?= e($user['Username']) ?>. Only the role <strong>admin</strong> can see this page.</p>

<?php require 'includes/footer.php'; ?>
