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
    public function renderTodoForm(array $todos, string $actionUrl, string $username = '', int $year = 0, string $clientTimezone = 'UTC'): string
    {
        $html = '<form method="POST" action="' . htmlspecialchars($actionUrl) . '" class="todo-form">';
        $html .= '<input type="hidden" name="client_timezone" class="client_timezone" value="' . htmlspecialchars($clientTimezone) . '">';
        $html .= '<input type="hidden" name="client_datetime" class="client_datetime" value="">';

        $html .= $this->renderTodos($todos, $username, $year, $clientTimezone);

        $html .= '<div class="todo-actions">';
        $html .= '<button type="submit" class="btn btn-primary">Save Changes</button>';
        $html .= '</div>';

        $html .= '<script src="/js/todo-form.js"></script>';

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
