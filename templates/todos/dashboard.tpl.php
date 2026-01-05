<div class="PagePanel">
    <h1>My Todos - <?= htmlspecialchars($year) ?></h1>

    <p>Welcome back, <?= htmlspecialchars($username) ?>!</p>

    <div class="todo-dashboard">
        <h2>Create New Project</h2>
        <form method="POST" action="" class="todo-create-project-form">
            <input type="hidden" name="action" value="create_project">
            <div class="form-group">
                <input type="text"
                       name="project_name"
                       placeholder="Enter project name (e.g., work, personal, 2026-goals)"
                       required
                       class="project-name-input"
                       autofocus>
                <button type="submit" class="btn btn-primary">Create Project</button>
            </div>
        </form>

        <?php if (isset($error_message) && !empty($error_message)): ?>
            <p class="error-message"><?= htmlspecialchars($error_message) ?></p>
        <?php endif; ?>

        <h2>Projects</h2>
        <?= $projectListHtml ?>
    </div>
</div>

