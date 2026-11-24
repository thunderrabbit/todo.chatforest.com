<div class="PagePanel">
    What's up <?= $username ?>? <br />
</div>
<h1>Welcome to the MarbleTrack3 Admin Dashboard</h1>
<p>This page can show numbers of workers, parts, snippets, etc</p>
<?php
if ($has_pending_migrations) {
        echo "<h3>Pending DB Migrations</h3>";
        echo "<a href='/admin/migrate_tables.php'>Click here to migrate tables</a>";
    }
?>
<h3>Notifications</h3>
<p><a href="/admin/notifications/">Notifications</a>
    (<?= (int) $due_notifications ?> due)</p>

<div class="fix">
    <p>Sentimental version: <?= $site_version ?></p>
</div>
