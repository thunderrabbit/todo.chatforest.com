<div class="PagePanel">
    <h1><a href="/do/" class="breadcrumb-link">..</a> / <?= htmlspecialchars($project) ?> - <?= htmlspecialchars($year) ?></h1>

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
            <input type="hidden" name="client_timezone" id="client_timezone" value="">
            <input type="hidden" name="client_datetime" id="client_datetime" value="">
            <div class="form-group">
                <input type="text" name="new_todo" placeholder="Enter todo description (optional: start with date like '01-jan-2025 Task name')" class="todo-input" size="80">
            </div>
            <button type="submit" class="btn btn-secondary">Add Todo</button>
        </form>
        <script>
            // Focus the input field when page loads
            window.addEventListener('DOMContentLoaded', function() {
                document.querySelector('.todo-input').focus();
            });

            // Update timezone and datetime on form submit
            document.querySelector('.todo-new-item').addEventListener('submit', function() {
                document.getElementById('client_timezone').value = Intl.DateTimeFormat().resolvedOptions().timeZone;

                const now = new Date();
                const year = now.getFullYear();
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const day = String(now.getDate()).padStart(2, '0');
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                const seconds = String(now.getSeconds()).padStart(2, '0');
                document.getElementById('client_datetime').value = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
            });
        </script>
    </div>

    <div class="todo-view">
        <?= $formHtml ?>
    </div>
</div>

