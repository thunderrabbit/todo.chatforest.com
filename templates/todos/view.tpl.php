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

    <div class="todo-view">
        <?= $formHtml ?>
    </div>
</div>

