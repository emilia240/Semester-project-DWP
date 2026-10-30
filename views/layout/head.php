<?php
/* The top of every HTML page (up to and including <body>). Needs $title. */
?>
<!doctype html>
<html lang="en" data-theme="tobehuman">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title === SITE_NAME ? SITE_NAME : $title . ' - ' . SITE_NAME) ?></title>
    <meta name="description" content="To Be Human is a calm place for work made by human hands. No ads, no AI images.">
    <link rel="preload" href="assets/fonts/fraunces-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="assets/css/app.css">
    <!-- No "defer": it must run before the page is drawn (see the comment in the file). -->
    <script src="assets/js/theme.js"></script>
</head>
<body class="min-h-screen bg-base-200 text-base-content antialiased">
<a href="#main" class="btn btn-primary btn-sm sr-only focus:not-sr-only focus:fixed focus:left-2 focus:top-2 focus:z-[100]">Skip to content</a>
