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

        // Editable content wrapper with data attributes
        $html .= '<span class="todo-editable" data-index="' . $index . '" data-createdate="' . htmlspecialchars($createDate ?? '') . '" data-description="' . htmlspecialchars($todo['description']) . '" data-completedate="' . htmlspecialchars($completeDate ?? '') . '">';

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

        $html .= '</span></label>';

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

        $html .= '        <script>
            // Helper function to set timezone/datetime before submitting
            function setTimezoneAndDatetime(form) {
                var timezoneInput = form.querySelector(".client_timezone");
                var datetimeInput = form.querySelector(".client_datetime");
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
            }

            // Update timezone/datetime inputs when form is submitted
            var todoForm = document.querySelector(".todo-form");
            if (todoForm) {
                todoForm.addEventListener("submit", function() {
                    setTimezoneAndDatetime(todoForm);
                });

                // Auto-save when checkbox is clicked via AJAX
                var checkboxes = todoForm.querySelectorAll(".todo-checkbox");
                checkboxes.forEach(function(checkbox) {
                    checkbox.addEventListener("change", function(e) {
                        var checkbox = e.target;
                        var todoItem = checkbox.closest(".todo-item");
                        var originalChecked = checkbox.checked; // After change event, this is the NEW state

                        // Set timezone/datetime
                        setTimezoneAndDatetime(todoForm);

                        // Build FormData for submission
                        var formData = new FormData(todoForm);

                        // Disable checkbox during save
                        checkbox.disabled = true;
                        todoItem.classList.add("saving");

                        // Send AJAX request
                        fetch(todoForm.action, {
                            method: "POST",
                            headers: {
                                "X-Requested-With": "XMLHttpRequest"
                            },
                            body: formData
                        })
                        .then(function(response) {
                            if (!response.ok) {
                                throw new Error("Save failed");
                            }
                            // Success - keep the checkbox state
                            checkbox.disabled = false;
                            todoItem.classList.remove("saving");
                            // Remove any existing error state
                            todoItem.classList.remove("todo-error");
                        })
                        .catch(function(error) {
                            // Failure - revert checkbox
                            checkbox.checked = !originalChecked;
                            checkbox.disabled = false;
                            todoItem.classList.remove("saving");
                            // Show error state
                            todoItem.classList.add("todo-error");
                            // Remove error state after animation
                            setTimeout(function() {
                                todoItem.classList.remove("todo-error");
                            }, 2000);
                        });
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
                            setTimezoneAndDatetime(todoForm);
                            todoForm.submit();
                        }
                    });
                }

                // Long-press to edit functionality
                var editableItems = todoForm.querySelectorAll(".todo-editable");
                editableItems.forEach(function(item) {
                    var longPressTimer = null;
                    var originalContent = null;
                    var todoItem = item.closest(".todo-item");
                    var index = item.dataset.index;

                    // Disable sortable while editing
                    function isEditing() {
                        return todoItem.classList.contains("editing");
                    }

                    // Start long press
                    item.addEventListener("mousedown", function(e) {
                        // Don\'t trigger on checkbox or drag handle
                        if (e.target.closest(".todo-checkbox") || e.target.closest(".drag-handle")) {
                            return;
                        }

                        longPressTimer = setTimeout(function() {
                            startEdit(item, index, todoItem, originalContent);
                        }, 500); // 500ms long press

                        originalContent = item.innerHTML;
                    });

                    // Cancel long press on mouseup
                    item.addEventListener("mouseup", function() {
                        if (longPressTimer) {
                            clearTimeout(longPressTimer);
                            longPressTimer = null;
                        }
                    });

                    // Touch support
                    item.addEventListener("touchstart", function(e) {
                        if (e.target.closest(".todo-checkbox") || e.target.closest(".drag-handle")) {
                            return;
                        }

                        longPressTimer = setTimeout(function() {
                            startEdit(item, index, todoItem, originalContent);
                        }, 500);

                        originalContent = item.innerHTML;
                    });

                    item.addEventListener("touchend", function() {
                        if (longPressTimer) {
                            clearTimeout(longPressTimer);
                            longPressTimer = null;
                        }
                    });
                });

                function startEdit(item, index, todoItem, originalContent) {
                    todoItem.classList.add("editing");

                    var createDate = item.dataset.createdate || "";
                    var description = item.dataset.description || "";
                    var completeDate = item.dataset.completedate || "";

                    var editHtml = \'<div class="todo-editing">\';
                    editHtml += \'<input type="text" class="todo-edit-field" placeholder="Start Date (e.g., 14:30:17 02-nov-2025)" value="\' + createDate + \'" data-field="createDate">\';
                    editHtml += \'<input type="text" class="todo-edit-field" placeholder="Description" value="\' + description + \'" data-field="description">\';
                    editHtml += \'<input type="text" class="todo-edit-field" placeholder="End Date (e.g., 15:45:30 05-nov-2025)" value="\' + completeDate + \'" data-field="completeDate">\';
                    editHtml += \'<div class="todo-edit-buttons">\';
                    editHtml += \'<button type="button" class="todo-edit-btn save">Save</button>\';
                    editHtml += \'<button type="button" class="todo-edit-btn cancel">Cancel</button>\';
                    editHtml += \'</div></div>\';

                    item.innerHTML = editHtml;

                    // Focus first field
                    var firstField = item.querySelector(".todo-edit-field");
                    if (firstField) {
                        firstField.focus();
                    }

                    // Save button
                    var saveBtn = item.querySelector(".todo-edit-btn.save");
                    saveBtn.addEventListener("click", function() {
                        saveEdit(item, index, todoItem);
                    });

                    // Cancel button
                    var cancelBtn = item.querySelector(".todo-edit-btn.cancel");
                    cancelBtn.addEventListener("click", function() {
                        cancelEdit(item, todoItem, originalContent);
                    });

                    // Enter key to save, Escape to cancel
                    item.querySelectorAll(".todo-edit-field").forEach(function(field) {
                        field.addEventListener("keydown", function(e) {
                            if (e.key === "Enter" && e.ctrlKey) {
                                saveEdit(item, index, todoItem);
                            } else if (e.key === "Escape") {
                                cancelEdit(item, todoItem, originalContent);
                            }
                        });
                    });
                }

                function saveEdit(item, index, todoItem) {
                    var fields = item.querySelectorAll(".todo-edit-field");
                    var data = {};

                    fields.forEach(function(field) {
                        data[field.dataset.field] = field.value;
                    });

                    // Update hidden inputs
                    var hiddenInputs = todoItem.querySelectorAll("input[type=hidden]");
                    hiddenInputs.forEach(function(hidden) {
                        var match = hidden.name.match(/todo_data\\[(\\d+)\\]\\[(\\w+)\\]/);
                        if (match && match[1] === index && data[match[2]] !== undefined) {
                            hidden.value = data[match[2]];
                        }
                    });

                    // Update dataset for display
                    item.dataset.createdate = data.createDate || "";
                    item.dataset.description = data.description || "";
                    item.dataset.completedate = data.completeDate || "";

                    // Revert to display mode (will be rebuilt by server on reload)
                    todoItem.classList.remove("editing");

                    // Auto-save
                    setTimezoneAndDatetime(todoForm);
                    todoForm.submit();
                }

                function cancelEdit(item, todoItem, originalContent) {
                    todoItem.classList.remove("editing");
                    item.innerHTML = originalContent;

                    // Re-attach long press listeners
                    attachEditListeners(item);
                }

                function attachEditListeners(item) {
                    var longPressTimer = null;
                    var originalContent = null;
                    var todoItem = item.closest(".todo-item");
                    var index = item.dataset.index;

                    item.addEventListener("mousedown", function(e) {
                        if (e.target.closest(".todo-checkbox") || e.target.closest(".drag-handle")) {
                            return;
                        }
                        longPressTimer = setTimeout(function() {
                            startEdit(item, index, todoItem, originalContent);
                        }, 500);
                        originalContent = item.innerHTML;
                    });

                    item.addEventListener("mouseup", function() {
                        if (longPressTimer) {
                            clearTimeout(longPressTimer);
                            longPressTimer = null;
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
