<div class="PagePanel">
    <h1><?= htmlspecialchars($project) ?> - <?= htmlspecialchars($year) ?></h1>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-error">
            <?= $error_message ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <?= $success_message ?>
        </div>
    <?php endif; ?>

    <div class="todo-new-form">
        <h2>Add New Todo</h2>
        <form method="POST" action="" class="todo-new-item">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" value="add_todo">
            <div class="form-group">
                <input type="text" name="new_todo" placeholder="Enter todo description (optional: start with date like '01-jan-2025 Task name')" class="todo-input" size="80">
            </div>
            <button type="submit" class="btn btn-secondary">Add Todo</button>
        </form>
    </div>

    <div class="todo-view">
        <?= $formHtml ?>
    </div>
</div>

