<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content=""/>
    <title><?= $page_title ?? 'MarbleTrack3 Admin' ?></title>
    <link rel="stylesheet" href="/css/styles.css">
    <link rel="stylesheet" href="/css/menu.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js" integrity="sha384-eeLEhtwdMwD3X9y+8P3Cn7Idl/M+w8H4uZqkgD/2eJVkWIN1yKzEj6XegJ9dL3q0" crossorigin="anonymous"></script>
</head>
<?php $menu_admin_count = (int) ($admin_count ?? 0); ?>
<body>
    <div class="NavBar">
        <a href="/">View Site</a> |
        <?php if ($menu_admin_count > 0): ?>
        <a href="/admin/" aria-label="Admin Site, <?= $menu_admin_count ?> waiting for an admin">Admin Site
            <span class="AdminCount" aria-hidden="true"><?= $menu_admin_count ?></span></a> |
        <?php else: ?>
        <a href="/admin/">Admin Site</a> |
        <?php endif; ?>
        <a href="/admin/workers">Workers</a> |
        <div class="dropdown">
            <a href="/profile/">Profile ▾</a>
            <div class="dropdown-menu">
                <a href="/logout/">Logout</a>
            </div>
        </div>
        <span class="timezone-display"></span>
    </div>
    <div class="PageWrapper">
        <?= $page_content ?>
    </div>
    <script src="/js/timezone.js"></script>
</body>
</html>
