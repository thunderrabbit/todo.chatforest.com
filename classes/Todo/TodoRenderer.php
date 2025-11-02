<?php

namespace Todo;

/**
 * TodoRenderer - renders todo items as HTML
 *
 * Handles rendering todo items with proper HTML markup,
 * including wiki-style links and checkboxes
 */
class TodoRenderer
{
    public function __construct(
        private \Config $config,
    ) {
    }

    /**
     * Render todos as HTML
     *
     * @param array $todos Array of todo items
     * @param string $username The username
     * @param int $year The year (YYYY)
     * @return string HTML content
     */
    public function renderTodos(array $todos, string $username, int $year): string
    {
        if (empty($todos)) {
            return '<p class="no-todos">No todos yet. Create your first one!</p>';
        }

        $html = '<ul class="todo-list">';

        foreach ($todos as $index => $todo) {
            $html .= $this->renderTodoItem($todo, $index, $username, $year);
        }

        $html .= '</ul>';

        return $html;
    }

    /**
     * Render a single todo item as HTML
     *
     * @param array $todo The todo item
     * @param int $index The index of the todo (used for form inputs)
     * @param string $username The username
     * @param int $year The year (YYYY)
     * @return string HTML content
     */
    private function renderTodoItem(array $todo, int $index, string $username, int $year): string
    {
        $isComplete = $todo['isComplete'];
        $createDate = $todo['createDate'];
        $description = htmlspecialchars($todo['description']);
        $completeDate = $todo['completeDate'];

        $html = '<li class="todo-item';
        if ($isComplete) {
            $html .= ' todo-complete';
        }
        $html .= '">';

        // Drag handle (six dots)
        $html .= '<span class="drag-handle">⋮⋮</span>';

        // Checkbox
        $html .= '<input type="checkbox" class="todo-checkbox"';
        $html .= ' name="todo[' . $index . ']"';
        $html .= ' id="todo-' . $index . '"';
        if ($isComplete) {
            $html .= ' checked';
        }
        $html .= '>';

        $html .= '<label for="todo-' . $index . '">';

        // Description with optional link
        $html .= '<span class="todo-description">';
        if ($todo['hasLink']) {
            $linkText = htmlspecialchars($todo['linkText']);
            $linkUrl = "/do/{$todo['linkFile']}";
            $html .= '<a href="' . htmlspecialchars($linkUrl) . '" class="todo-link">';
            $html .= '📄 ' . $linkText;
            $html .= '</a>';
        } else {
            $html .= $description;
        }
        $html .= '</span>';

        // Dates
        $html .= '<span class="todo-dates">';
        if ($createDate) {
            $html .= '<span class="todo-date-create">' . htmlspecialchars($createDate) . '</span>';
        }
        if ($isComplete && $completeDate) {
            $html .= '<span class="todo-date-complete">' . htmlspecialchars($completeDate) . '</span>';
        }
        $html .= '</span>';

        $html .= '</label>';

        // Hidden inputs to preserve todo data
        $html .= '<input type="hidden" name="todo_data[' . $index . '][description]" value="' . htmlspecialchars($todo['description']) . '">';
        $html .= '<input type="hidden" name="todo_data[' . $index . '][createDate]" value="' . htmlspecialchars($todo['createDate'] ?? '') . '">';
        $html .= '<input type="hidden" name="todo_data[' . $index . '][completeDate]" value="' . htmlspecialchars($todo['completeDate'] ?? '') . '">';
        $html .= '<input type="hidden" name="todo_data[' . $index . '][hasLink]" value="' . ($todo['hasLink'] ? '1' : '0') . '">';
        $html .= '<input type="hidden" name="todo_data[' . $index . '][linkText]" value="' . htmlspecialchars($todo['linkText']) . '">';
        $html .= '<input type="hidden" name="todo_data[' . $index . '][linkFile]" value="' . htmlspecialchars($todo['linkFile']) . '">';

        $html .= '</li>';

        return $html;
    }

    /**
     * Render a todo form for editing
     *
     * @param array $todos Array of todo items
     * @param string $actionUrl The form action URL
     * @param string $csrfToken The CSRF token
     * @return string HTML form content
     */
    public function renderTodoForm(array $todos, string $actionUrl, string $csrfToken, string $username = '', int $year = 0): string
    {
        $html = '<form method="POST" action="' . htmlspecialchars($actionUrl) . '" class="todo-form">';
        $html .= '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '">';
        $html .= '<input type="hidden" name="client_timezone" class="client_timezone" value="">';
        $html .= '<input type="hidden" name="client_datetime" class="client_datetime" value="">';

        $html .= $this->renderTodos($todos, $username, $year);

        $html .= '<div class="todo-actions">';
        $html .= '<button type="submit" class="btn btn-primary">Save Changes</button>';
        $html .= '</div>';

        $html .= '<script>
            // Update timezone/datetime inputs when form is submitted
            var todoForm = document.querySelector(".todo-form");
            if (todoForm) {
                todoForm.addEventListener("submit", function() {
                    var timezoneInput = todoForm.querySelector(".client_timezone");
                    var datetimeInput = todoForm.querySelector(".client_datetime");
                    if (timezoneInput && datetimeInput) {
                        timezoneInput.value = Intl.DateTimeFormat().resolvedOptions().timeZone;
                        const now = new Date();
                        const year = now.getFullYear();
                        const month = String(now.getMonth() + 1).padStart(2, "0");
                        const day = String(now.getDate()).padStart(2, "0");
                        const hours = String(now.getHours()).padStart(2, "0");
                        const minutes = String(now.getMinutes()).padStart(2, "0");
                        const seconds = String(now.getSeconds()).padStart(2, "0");
                        datetimeInput.value = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
                    }
                });

                // Auto-save when checkbox is clicked
                var checkboxes = todoForm.querySelectorAll(".todo-checkbox");
                checkboxes.forEach(function(checkbox) {
                    checkbox.addEventListener("change", function() {
                        todoForm.submit();
                    });
                });

                // Initialize sortable drag and drop
                var todoList = todoForm.querySelector(".todo-list");
                if (todoList && typeof Sortable !== "undefined") {
                    var sortable = new Sortable(todoList, {
                        handle: ".drag-handle",
                        animation: 150,
                        onEnd: function() {
                            // Reorder form inputs to match new DOM order
                            var todoItems = todoList.querySelectorAll(".todo-item");
                            var newIndex = 0;
                            todoItems.forEach(function(item) {
                                // Get the actual form index from the checkbox
                                var checkbox = item.querySelector(".todo-checkbox");
                                if (checkbox) {
                                    var oldIndex = checkbox.name.match(/\[(\d+)\]/)[1];

                                    // Update checkbox name
                                    checkbox.name = "todo[" + newIndex + "]";
                                    checkbox.id = "todo-" + newIndex;

                                    // Update label for
                                    var label = item.querySelector("label");
                                    if (label) {
                                        label.setAttribute("for", "todo-" + newIndex);
                                    }

                                    // Update all hidden inputs
                                    var hiddenInputs = item.querySelectorAll("input[type=hidden]");
                                    hiddenInputs.forEach(function(hidden) {
                                        if (hidden.name.startsWith("todo_data[" + oldIndex + "]")) {
                                            var newName = hidden.name.replace(/^todo_data\[\d+\]/, "todo_data[" + newIndex + "]");
                                            hidden.name = newName;
                                        }
                                    });
                                }
                                newIndex++;
                            });

                            // Auto-save after reordering
                            todoForm.submit();
                        }
                    });
                }
            }
        </script>';

        $html .= '</form>';

        return $html;
    }

    /**
     * Render project list
     *
     * @param array $projects Array of project names
     * @param string $username The username
     * @param int $year The year (YYYY)
     * @return string HTML content
     */
    public function renderProjectList(array $projects, string $username, int $year): string
    {
        if (empty($projects)) {
            return '<p class="no-projects">No projects yet.</p>';
        }

        $html = '<ul class="project-list">';

        foreach ($projects as $project) {
            $url = "/do/{$project}";
            $displayName = $this->formatProjectName($project);
            $html .= '<li><a href="' . htmlspecialchars($url) . '">📁 ' . htmlspecialchars($displayName) . '</a></li>';
        }

        $html .= '</ul>';

        return $html;
    }

    /**
     * Format project name for display
     *
     * @param string $project The project name
     * @return string Formatted name
     */
    private function formatProjectName(string $project): string
    {
        // Replace underscores and hyphens with spaces
        $formatted = str_replace(['_', '-'], ' ', $project);
        // Capitalize first letter of each word
        return ucwords($formatted);
    }
}
