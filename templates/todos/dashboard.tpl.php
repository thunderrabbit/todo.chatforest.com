<div class="PagePanel">
    <h1>My Todos - <?= htmlspecialchars($year) ?></h1>

    <p>Welcome back, <?= htmlspecialchars($username) ?>!</p>

    <div class="todo-dashboard">
        <h2>Projects</h2>
        <?= $projectListHtml ?>
    </div>
</div>

