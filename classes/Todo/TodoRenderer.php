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
     * @param string $clientTimezone Client timezone (e.g., "America/New_York")
     * @return string HTML content
     */
    public function renderTodos(array $todos, string $username, int $year, string $clientTimezone = 'UTC'): string
    {
        // Filter todos based on age rules
        $filteredTodos = $this->filterTodos($todos, $clientTimezone);

        if (empty($filteredTodos)) {
            return '<p class="no-todos">No todos yet. Create your first one!</p>';
        }

        $html = '<ul class="todo-list">';

        foreach ($filteredTodos as $index => $todo) {
            $html .= $this->renderTodoItem($todo, $index, $username, $year, $clientTimezone);
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
     * @param string $clientTimezone Client timezone (e.g., "America/New_York")
     * @return string HTML content
     */
    private function renderTodoItem(array $todo, int $index, string $username, int $year, string $clientTimezone = 'UTC'): string
    {
        $isComplete = $todo['isComplete'];
        $createDate = $todo['createDate'];
        $description = htmlspecialchars($todo['description']);
        $completeDate = $todo['completeDate'];

        // Check if item should be styled as old (started over 1 week ago, has time component, not a link)
        $isOld = $this->isItemOld($todo, $clientTimezone);

        // Check if item is in the future (for dimming)
        $isFuture = $this->isItemFuture($todo, $clientTimezone);

        $html = '<li class="todo-item';
        if ($isComplete) {
            $html .= ' todo-complete';
        }
        if ($isOld) {
            $html .= ' todo-old';
        }
        if ($isFuture) {
            $html .= ' todo-future';
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
        $descClass = 'todo-description';
        $descData = '';
        if ($todo['hasLink']) {
            $descClass .= ' todo-dropzone';
            $descData = ' data-link-file="' . htmlspecialchars($todo['linkFile']) . '"';
        }
        $html .= '<span class="' . $descClass . '"' . $descData . '>';
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

        // Delete button
        $html .= '<button type="button" class="todo-delete-btn" data-index="' . $index . '" data-createdate="' . htmlspecialchars($createDate ?? '') . '" data-description="' . htmlspecialchars($todo['description']) . '" title="Delete">🗑️</button>';

        // Hidden inputs to preserve todo data
        // Original values are used to match items even after editing
        $originalCreateDate = $todo['createDate'] ?? '';
        $originalDescription = $todo['description'] ?? '';
        $html .= '<input type="hidden" name="todo_data[' . $index . '][originalCreateDate]" value="' . htmlspecialchars($originalCreateDate) . '">';
        $html .= '<input type="hidden" name="todo_data[' . $index . '][originalDescription]" value="' . htmlspecialchars($originalDescription) . '">';
        $html .= '<input type="hidden" name="todo_data[' . $index . '][description]" value="' . htmlspecialchars($todo['description']) . '">';
        $html .= '<input type="hidden" name="todo_data[' . $index . '][createDate]" value="' . htmlspecialchars($todo['createDate'] ?? '') . '">';
        $html .= '<input type="hidden" name="todo_data[' . $index . '][completeDate]" value="' . htmlspecialchars($todo['completeDate'] ?? '') . '">';
        $html .= '<input type="hidden" name="todo_data[' . $index . '][hasLink]" value="' . ($todo['hasLink'] ? '1' : '0') . '">';
        $html .= '<input type="hidden" name="todo_data[' . $index . '][linkText]" value="' . htmlspecialchars($todo['linkText']) . '">';
        $html .= '<input type="hidden" name="todo_data[' . $index . '][linkFile]" value="' . htmlspecialchars($todo['linkFile']) . '">';
        $html .= '<input type="hidden" name="todo_data[' . $index . '][recurringMarker]" value="' . htmlspecialchars($todo['recurringMarker'] ?? '') . '">';

        $html .= '</li>';

        return $html;
    }

    /**
     * Render a todo form for editing
     *
     * @param array $todos Array of todo items
     * @param string $actionUrl The form action URL
     * @param string $csrfToken The CSRF token
     * @param string $username The username
     * @param int $year The year (YYYY)
     * @param string $clientTimezone Client timezone (e.g., "America/New_York")
     * @return string HTML form content
     */
    public function renderTodoForm(array $todos, string $actionUrl, string $csrfToken, string $username = '', int $year = 0, string $clientTimezone = 'UTC'): string
    {
        $html = '<form method="POST" action="' . htmlspecialchars($actionUrl) . '" class="todo-form">';
        $html .= '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '">';
        $html .= '<input type="hidden" name="client_timezone" class="client_timezone" value="' . htmlspecialchars($clientTimezone) . '">';
        $html .= '<input type="hidden" name="client_datetime" class="client_datetime" value="">';

        $html .= $this->renderTodos($todos, $username, $year, $clientTimezone);

        $html .= '<div class="todo-actions">';
        $html .= '<button type="submit" class="btn btn-primary">Save Changes</button>';
        $html .= '</div>';

        $html .= '        <script>
            // Helper function to set timezone/datetime before submitting
            function setTimezoneAndDatetime(form) {
                var timezoneInput = form.querySelector(".client_timezone");
                var datetimeInput = form.querySelector(".client_datetime");
                if (timezoneInput && datetimeInput) {
                    var timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
                    timezoneInput.value = timezone;
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
                // Set timezone immediately on page load
                var timezoneInput = todoForm.querySelector(".client_timezone");
                if (timezoneInput) {
                    var clientTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
                    var currentUrl = new URL(window.location.href);
                    // If timezone not in URL and input is empty/UTC, reload with timezone
                    if (!currentUrl.searchParams.has("timezone") && (!timezoneInput.value || timezoneInput.value === "UTC")) {
                        currentUrl.searchParams.set("timezone", clientTimezone);
                        window.location.href = currentUrl.toString();
                        // Page will navigate, no need to continue
                    } else {
                        // Always update the input with current timezone
                        timezoneInput.value = clientTimezone;
                    }
                }

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
                        var index = checkbox.name.match(/\[(\d+)\]/)[1];

                        // Update completion date in the form based on checkbox state
                        var hiddenInputs = todoItem.querySelectorAll("input[type=hidden]");
                        var completeDateInput = null;
                        for (var i = 0; i < hiddenInputs.length; i++) {
                            if (hiddenInputs[i].name === "todo_data[" + index + "][completeDate]") {
                                completeDateInput = hiddenInputs[i];
                                break;
                            }
                        }
                        if (completeDateInput) {
                            if (checkbox.checked) {
                                // If being checked and does not already have a completion date, set it
                                var hasCompletionDate = completeDateInput.value && completeDateInput.value.trim().length > 0;
                                if (!hasCompletionDate) {
                                    // Set timezone/datetime first to get current client time
                                    setTimezoneAndDatetime(todoForm);
                                    var datetimeInput = todoForm.querySelector(".client_datetime");
                                    var timezoneInput = todoForm.querySelector(".client_timezone");

                                    if (datetimeInput && timezoneInput) {
                                        // Parse the datetime and format it as HH:MM:SS DD-Mon-YYYY
                                        var clientDatetime = datetimeInput.value; // Format: "YYYY-MM-DD HH:MM:SS"
                                        if (clientDatetime) {
                                            var dt = new Date(clientDatetime);
                                            var hours = String(dt.getHours()).padStart(2, "0");
                                            var minutes = String(dt.getMinutes()).padStart(2, "0");
                                            var seconds = String(dt.getSeconds()).padStart(2, "0");
                                            var day = String(dt.getDate()).padStart(2, "0");
                                            var months = ["jan", "feb", "mar", "apr", "may", "jun", "jul", "aug", "sep", "oct", "nov", "dec"];
                                            var month = months[dt.getMonth()];
                                            var year = dt.getFullYear();
                                            completeDateInput.value = hours + ":" + minutes + ":" + seconds + " " + day + "-" + month + "-" + year;

                                            // Also update the display
                                            var completeDateSpan = todoItem.querySelector(".todo-date-complete");
                                            if (completeDateSpan) {
                                                completeDateSpan.textContent = completeDateInput.value;
                                            }
                                        }
                                    }
                                }
                                // If it already has a date, leave it alone (preserves existing completion date)
                            } else {
                                // If being unchecked, clear the completion date
                                completeDateInput.value = "";
                                var completeDateSpan = todoItem.querySelector(".todo-date-complete");
                                if (completeDateSpan) {
                                    completeDateSpan.remove();
                                }
                            }
                        }

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
                            return response.json();
                        })
                        .then(function(data) {
                            // Success - keep the checkbox state
                            checkbox.disabled = false;
                            todoItem.classList.remove("saving");
                            // Remove any existing error state
                            todoItem.classList.remove("todo-error");

                            // Update CSRF token in all forms on the page
                            if (data.csrf_token) {
                                var allCsrfInputs = document.querySelectorAll("input[name=\\"csrf_token\\"]");
                                allCsrfInputs.forEach(function(csrfInput) {
                                    csrfInput.value = data.csrf_token;
                                });
                            }
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
                    var targetDropzone = null; // Track which dropzone we\'re over
                    var sortable = new Sortable(todoList, {
                        handle: ".drag-handle",
                        animation: 150,
                        onChoose: function(e) {
                            // Clear any previous highlights
                            var dropzones = todoList.querySelectorAll(".todo-dropzone");
                            dropzones.forEach(function(dropzone) {
                                dropzone.classList.remove("sortable-drag-over");
                            });
                            targetDropzone = null;
                        },
                        onMove: function(e) {
                            // Highlight the dropzone if dragging over it
                            var dropzones = todoList.querySelectorAll(".todo-dropzone");
                            dropzones.forEach(function(dropzone) {
                                dropzone.classList.remove("sortable-drag-over");
                            });

                            // Check if we\'re dragging over a dropzone using the related item (the item being hovered)
                            if (e.related && e.related.querySelector(".todo-dropzone")) {
                                var dropzone = e.related.querySelector(".todo-dropzone");
                                dropzone.classList.add("sortable-drag-over");
                                targetDropzone = dropzone;
                            } else {
                                targetDropzone = null;
                            }
                        },
                        onUnchoose: function(e) {
                            // When drag ends, remove highlights from all dropzones
                            var dropzones = todoList.querySelectorAll(".todo-dropzone");
                            dropzones.forEach(function(dropzone) {
                                dropzone.classList.remove("sortable-drag-over");
                            });
                            // Don\'t clear targetDropzone here - let onEnd handle it
                        },
                        onEnd: function(e) {
                            // Check if this was a drop onto a dropzone
                            if (targetDropzone !== null) {
                                // This is a move to another list - handle it specially
                                var targetFile = targetDropzone.dataset.linkFile;

                                // Get the data from the dragged item
                                var draggedItem = e.item;
                                var draggedIndex = draggedItem.querySelector(".todo-checkbox").name.match(/\[(\d+)\]/)[1];
                                var todoData = {};

                                // Get all hidden inputs for this item
                                var hiddenInputs = draggedItem.querySelectorAll("input[type=hidden]");
                                hiddenInputs.forEach(function(hidden) {
                                    var match = hidden.name.match(/todo_data\\[(\\d+)\\]\\[(\\w+)\\]/);
                                    if (match && match[1] === draggedIndex) {
                                        todoData[match[2]] = hidden.value;
                                    }
                                });
                                todoData.isComplete = draggedItem.querySelector(".todo-checkbox").checked;

                                // Convert hasLink from "1"/"0" to boolean if needed
                                if (todoData.hasLink !== undefined) {
                                    todoData.hasLink = todoData.hasLink === "1";
                                } else {
                                    todoData.hasLink = false;
                                }

                                // Remove the item from current list (optimistic update)
                                draggedItem.remove();

                                // Send AJAX request to move the item
                                setTimezoneAndDatetime(todoForm);
                                var formData = new FormData(todoForm);
                                formData.append("action", "move_todo");
                                formData.append("target_file", targetFile);
                                formData.append("todo_description", todoData.description);
                                formData.append("todo_createDate", todoData.createDate);
                                formData.append("todo_completeDate", todoData.completeDate);
                                formData.append("todo_isComplete", todoData.isComplete ? "1" : "0");
                                formData.append("todo_hasLink", todoData.hasLink ? "1" : "0");
                                formData.append("todo_linkText", todoData.linkText || "");
                                formData.append("todo_linkFile", todoData.linkFile || "");

                                fetch(todoForm.action, {
                                    method: "POST",
                                    headers: {
                                        "X-Requested-With": "XMLHttpRequest"
                                    },
                                    body: formData
                                })
                                .then(function(response) {
                                    if (!response.ok) {
                                        throw new Error("Move failed");
                                    }
                                    return response.json();
                                })
                                .then(function(data) {
                                    // Update CSRF token if provided
                                    if (data.csrf_token) {
                                        var allCsrfInputs = document.querySelectorAll("input[name=\\"csrf_token\\"]");
                                        allCsrfInputs.forEach(function(csrfInput) {
                                            csrfInput.value = data.csrf_token;
                                        });
                                    }
                                })
                                .catch(function(error) {
                                    // Restore the item on error
                                    todoList.appendChild(draggedItem);
                                    draggedItem.classList.add("todo-error");
                                    setTimeout(function() {
                                        draggedItem.classList.remove("todo-error");
                                    }, 2000);
                                });

                                targetDropzone = null;
                                return;
                            }

                            // Regular reordering within the same list
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

                            // Auto-save after reordering via AJAX
                            setTimezoneAndDatetime(todoForm);

                            // Build FormData for submission
                            var formData = new FormData(todoForm);

                            // Show saving state on the entire list
                            todoList.classList.add("saving");

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
                                return response.json();
                            })
                            .then(function(data) {
                                // Success - remove saving state
                                todoList.classList.remove("saving");
                                todoList.classList.remove("todo-error");

                                // Update CSRF token in all forms on the page
                                if (data.csrf_token) {
                                    var allCsrfInputs = document.querySelectorAll("input[name=\\"csrf_token\\"]");
                                    allCsrfInputs.forEach(function(csrfInput) {
                                        csrfInput.value = data.csrf_token;
                                    });
                                }
                            })
                            .catch(function(error) {
                                // Failure - show error state
                                todoList.classList.remove("saving");
                                todoList.classList.add("todo-error");
                                // Remove error state after animation
                                setTimeout(function() {
                                    todoList.classList.remove("todo-error");
                                }, 2000);
                            });
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

                // Delete button functionality
                var deleteButtons = todoForm.querySelectorAll(".todo-delete-btn");

                // Attach click handlers to delete buttons
                deleteButtons.forEach(function(deleteBtn) {
                    deleteBtn.addEventListener("click", function(e) {
                        e.stopPropagation(); // Prevent triggering other click handlers
                        var todoItem = deleteBtn.closest(".todo-item");

                        // Show confirmation dialog
                        if (!confirm("Delete?")) {
                            return; // User cancelled
                        }

                        // Get todo data from the item
                        var checkbox = todoItem.querySelector(".todo-checkbox");
                        var index = checkbox.name.match(/\[(\d+)\]/)[1];
                        var hiddenInputs = todoItem.querySelectorAll("input[type=hidden]");
                        var todoData = {};

                        // Extract todo data from hidden inputs
                        hiddenInputs.forEach(function(hidden) {
                            var match = hidden.name.match(/todo_data\[(\d+)\]\[(\w+)\]/);
                            if (match && match[1] === index) {
                                todoData[match[2]] = hidden.value;
                            }
                        });

                        // Use original values for matching (same as in save)
                        var originalCreateDate = todoData.originalCreateDate || todoData.createDate || "";
                        var originalDescription = todoData.originalDescription || todoData.description || "";

                        // Show deleting state
                        todoItem.classList.add("saving");

                        // Set timezone/datetime
                        setTimezoneAndDatetime(todoForm);

                        // Build FormData for deletion
                        var formData = new FormData(todoForm);
                        formData.append("action", "delete_todo");
                        formData.append("todo_originalCreateDate", originalCreateDate);
                        formData.append("todo_originalDescription", originalDescription);

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
                                throw new Error("Delete failed");
                            }
                            return response.json();
                        })
                        .then(function(data) {
                            // Success - remove the item from DOM
                            todoItem.remove();
                            todoItem.classList.remove("saving");

                            // Update CSRF token if provided
                            if (data.csrf_token) {
                                var allCsrfInputs = document.querySelectorAll("input[name=\\"csrf_token\\"]");
                                allCsrfInputs.forEach(function(csrfInput) {
                                    csrfInput.value = data.csrf_token;
                                });
                            }

                            // If no todos left, reload the page to show empty state
                            var remainingTodos = todoForm.querySelectorAll(".todo-item");
                            if (remainingTodos.length === 0) {
                                window.location.reload();
                            }
                        })
                        .catch(function(error) {
                            // Failure - show error state
                            todoItem.classList.remove("saving");
                            todoItem.classList.add("todo-error");
                            setTimeout(function() {
                                todoItem.classList.remove("todo-error");
                            }, 2000);
                        });
                    });
                });
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

    /**
     * Filter todos based on age rules and future time rules
     *
     * @param array $todos Array of todo items
     * @param string $clientTimezone Client timezone
     * @return array Filtered todos
     */
    private function filterTodos(array $todos, string $clientTimezone): array
    {
        $filtered = [];
        $now = new \DateTime('now', new \DateTimeZone($clientTimezone));

        foreach ($todos as $todo) {
            // Skip completed todos (they will be handled separately)
            if ($todo['isComplete']) {
                // Hide completed items if completeDate exists, has time, and is > 5 minutes old
                if (!empty($todo['completeDate'])) {
                    $completeDateTime = $this->parseDate($todo['completeDate'], $clientTimezone);
                    if ($completeDateTime !== null && $this->hasTimeComponent($todo['completeDate'])) {
                        $age = $now->getTimestamp() - $completeDateTime->getTimestamp();
                        if ($age > 300) { // More than 5 minutes
                            continue; // Skip this item
                        }
                    }
                }
                $filtered[] = $todo;
                continue;
            }

            // For incomplete todos, check future time rules
            if (!empty($todo['createDate']) && $this->hasTimeComponent($todo['createDate'])) {
                $createDateTime = $this->parseDate($todo['createDate'], $clientTimezone);
                if ($createDateTime !== null) {
                    // Hide items more than 12 hours in the future
                    $secondsUntil = $createDateTime->getTimestamp() - $now->getTimestamp();
                    if ($secondsUntil > 12 * 3600) { // More than 12 hours
                        continue; // Skip this item
                    }

                    // Also hide items started > 2 weeks ago (old past items)
                    if ($secondsUntil < 0 && abs($secondsUntil) > 14 * 24 * 3600) {
                        continue; // Skip this item
                    }
                }
            }

            // Incomplete items with links are never hidden (except by future time rules above)
            if ($todo['hasLink']) {
                $filtered[] = $todo;
                continue;
            }

            $filtered[] = $todo;
        }

        return $filtered;
    }

    /**
     * Check if item is in the future (>4 hours) and should be dimmed
     *
     * @param array $todo The todo item
     * @param string $clientTimezone Client timezone
     * @return bool True if item should be dimmed
     */
    private function isItemFuture(array $todo, string $clientTimezone): bool
    {
        // Only check incomplete todos
        if ($todo['isComplete']) {
            return false;
        }

        // Items without createDate are not future
        if (empty($todo['createDate'])) {
            return false;
        }

        // Items without time component are not future
        if (!$this->hasTimeComponent($todo['createDate'])) {
            return false;
        }

        $createDateTime = $this->parseDate($todo['createDate'], $clientTimezone);
        if ($createDateTime === null) {
            return false;
        }

        $now = new \DateTime('now', new \DateTimeZone($clientTimezone));
        $secondsUntil = $createDateTime->getTimestamp() - $now->getTimestamp();

        // More than 4 hours in the future
        return $secondsUntil > 4 * 3600;
    }

    /**
     * Check if item should be styled as old (started over 1 week ago)
     *
     * @param array $todo The todo item
     * @param string $clientTimezone Client timezone
     * @return bool True if item should be styled as old
     */
    private function isItemOld(array $todo, string $clientTimezone): bool
    {
        // Items with links are exempt from graying
        if ($todo['hasLink']) {
            return false;
        }

        // Items without createDate are not old
        if (empty($todo['createDate'])) {
            return false;
        }

        // Items without time component are not old
        if (!$this->hasTimeComponent($todo['createDate'])) {
            return false;
        }

        $createDateTime = $this->parseDate($todo['createDate'], $clientTimezone);
        if ($createDateTime === null) {
            return false;
        }

        $now = new \DateTime('now', new \DateTimeZone($clientTimezone));
        $age = $now->getTimestamp() - $createDateTime->getTimestamp();

        // More than 1 week (7 days)
        return $age > 7 * 24 * 3600;
    }

    /**
     * Check if date string has a time component
     *
     * @param string $dateStr Date string (e.g., "14:30:17 02-nov-2025" or "02-nov-2025")
     * @return bool True if date has time component
     */
    private function hasTimeComponent(string $dateStr): bool
    {
        // Check for HH:MM:SS pattern at the start
        return preg_match('/^\d{2}:\d{2}:\d{2}\s+/', $dateStr) === 1;
    }

    /**
     * Parse date string to DateTime object
     *
     * Supports formats:
     * - "HH:MM:SS DD-mon-YYYY" (e.g., "14:30:17 02-nov-2025")
     * - "DD-mon-YYYY" (e.g., "02-nov-2025")
     *
     * @param string $dateStr Date string
     * @param string $timezone Timezone string
     * @return \DateTime|null DateTime object or null if parsing fails
     */
    private function parseDate(string $dateStr, string $timezone): ?\DateTime
    {
        $dateStr = trim($dateStr);
        if (empty($dateStr)) {
            return null;
        }

        try {
            $timezoneObj = new \DateTimeZone($timezone);
        } catch (\Exception $e) {
            // Invalid timezone, fallback to UTC
            $timezoneObj = new \DateTimeZone('UTC');
        }

        $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];

        // Check if it has time component (HH:MM:SS DD-mon-YYYY)
        if (preg_match('/^(\d{2}:\d{2}:\d{2})\s+(\d{2})-([a-z]{3})-(\d{4})$/i', $dateStr, $matches)) {
            $timePart = $matches[1];
            $day = $matches[2];
            $mon = strtolower($matches[3]);
            $year = $matches[4];

            $monIndex = array_search($mon, $months);
            if ($monIndex !== false) {
                $monNum = str_pad($monIndex + 1, 2, '0', STR_PAD_LEFT);
                $phpDateStr = "$year-$monNum-$day $timePart";
                try {
                    return new \DateTime($phpDateStr, $timezoneObj);
                } catch (\Exception $e) {
                    return null;
                }
            }
        } else {
            // Just date part (DD-mon-YYYY)
            if (preg_match('/^(\d{2})-([a-z]{3})-(\d{4})$/i', $dateStr, $matches)) {
                $day = $matches[1];
                $mon = strtolower($matches[2]);
                $year = $matches[3];

                $monIndex = array_search($mon, $months);
                if ($monIndex !== false) {
                    $monNum = str_pad($monIndex + 1, 2, '0', STR_PAD_LEFT);
                    $phpDateStr = "$year-$monNum-$day 12:00:00";
                    try {
                        return new \DateTime($phpDateStr, $timezoneObj);
                    } catch (\Exception $e) {
                        return null;
                    }
                }
            }
        }

        return null;
    }
}
