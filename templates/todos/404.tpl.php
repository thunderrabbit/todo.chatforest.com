<div class="PagePanel">
    <h1>Todo Not Found</h1>

    <p>The todo file for project "<?= htmlspecialchars($project) ?>" in year <?= htmlspecialchars($year) ?> could not be found.</p>

    <?php if (isset($error)): ?>
        <p><small>Error: <?= htmlspecialchars($error) ?></small></p>
    <?php endif; ?>

    <p><a href="/do/">Return to Todo Dashboard</a></p>
</div>

