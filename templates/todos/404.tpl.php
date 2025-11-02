<div class="PagePanel">
    <h1>Todo Not Found</h1>

    <p>The todo file for project "<?= htmlspecialchars($project) ?>" in year <?= htmlspecialchars($year) ?> could not be found.</p>

    <?php if (isset($error)): ?>
        <p><small>Error: <?= htmlspecialchars($error) ?></small></p>
    <?php endif; ?>

    <div class="todo-404-actions">
        <form method="POST" action="" class="todo-create-project">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" value="create_project">
            <button type="submit" class="btn btn-primary">Create This Project</button>
        </form>
        <p><a href="/do/" class="btn-link">Return to Todo Dashboard</a></p>
    </div>
</div>

