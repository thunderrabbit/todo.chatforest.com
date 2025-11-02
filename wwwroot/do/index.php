<?php

# Must include here because DH runs FastCGI https://www.phind.com/search?cache=zfj8o8igbqvaj8cm91wp1b7k
# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

// Check if user is logged in
if (!$is_logged_in->isLoggedIn()) {
    header("Location: /login/");
    exit;
}

// Get the logged in username
$username = $is_logged_in->getLoggedInUsername();
$currentYear = date('Y'); // Default to current year

// Parse the URL to determine what we're showing
$uri_path = $_SERVER['REQUEST_URI'] ?? '';
$error_message = '';
$success_message = '';

// Use TodoReader and TodoWriter
$todoReader = new \Todo\TodoReader($config);
$todoWriter = new \Todo\TodoWriter($config);
$todoRenderer = new \Todo\TodoRenderer($config);

// Parse URL first to get project info
// URL format: /do/project or /do/username/year/project
// With .htaccess, we need to handle the path properly
$pathParts = explode('/', trim(parse_url($uri_path, PHP_URL_PATH), '/'));
array_shift($pathParts); // Remove 'do' from the path

if (empty($pathParts)) {
    // Just /do/ - show dashboard
    $project = null;
} else {
    $firstPart = $pathParts[0];

    // Check if first part is a year (YYYY)
    if (is_numeric($firstPart) && strlen($firstPart) === 4) {
        $currentYear = intval($firstPart);
        $project = $pathParts[1] ?? 'main';
    } elseif (count($pathParts) >= 2 && is_numeric($pathParts[1]) && strlen($pathParts[1]) === 4) {
        // Format: /do/project/YYYY
        $project = $firstPart;
        $currentYear = intval($pathParts[1]);
    } else {
        // Just a project name
        $project = $firstPart;
    }
}

// Handle creating a new project file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_project' && $project !== null) {
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!$csrfProtect->validateToken($csrf_token)) {
        $error_message = "Invalid security token. Please try again.";
    } else {
        try {
            // Create empty project file
            $todoWriter->createEmptyFile($username, $currentYear, $project);
            // Redirect to the newly created project
            header("Location: /do/{$project}");
            exit;
        } catch (\Exception $e) {
            $error_message = "Error creating project: " . $e->getMessage();
        }
    }
}

// Handle moving todo to another list
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'move_todo' && $project !== null) {
    $csrf_token = $_POST['csrf_token'] ?? '';

    // Check if this is an AJAX request
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

    if (!$csrfProtect->validateToken($csrf_token)) {
        $error_message = "Invalid security token. Please try again.";
    } else {
        $targetFile = $_POST['target_file'] ?? '';

        if (empty($targetFile)) {
            $error_message = "Target file not specified.";
        } else {
            try {
                // Get the todo data from POST
                $todoDescription = $_POST['todo_description'] ?? '';
                $todoCreateDate = $_POST['todo_createDate'] ?? '';
                $todoCompleteDate = $_POST['todo_completeDate'] ?? '';
                $todoIsComplete = !empty($_POST['todo_isComplete']);
                $todoHasLink = !empty($_POST['todo_hasLink']);
                $todoLinkText = $_POST['todo_linkText'] ?? '';
                $todoLinkFile = $_POST['todo_linkFile'] ?? '';

                // First, remove from current list
                $rawContent = $todoReader->readRawContent($username, $currentYear, $project);
                $currentTodos = $todoReader->parseTodos($rawContent);

                // Find and remove the item (match by description and createDate)
                $updatedTodos = [];
                foreach ($currentTodos as $todo) {
                    if ($todo['description'] === $todoDescription && $todo['createDate'] === $todoCreateDate) {
                        // Skip this one - it\'s being moved
                        continue;
                    }
                    $updatedTodos[] = $todo;
                }

                // Write updated current list
                $todoWriter->writeTodos($username, $currentYear, $project, $updatedTodos);

                // Now add to target list
                try {
                    $targetContent = $todoReader->readRawContent($username, $currentYear, $targetFile);
                    $targetTodos = $todoReader->parseTodos($targetContent);
                } catch (\Exception $e) {
                    // Target file doesn\'t exist - create it
                    $targetTodos = [];
                }

                // Add the moved item to target list
                $targetTodos[] = [
                    'isComplete' => $todoIsComplete,
                    'createDate' => $todoCreateDate,
                    'description' => $todoDescription,
                    'completeDate' => $todoCompleteDate,
                    'hasLink' => $todoHasLink,
                    'linkText' => $todoLinkText,
                    'linkFile' => $todoLinkFile,
                ];

                // Write target list
                $todoWriter->writeTodos($username, $currentYear, $targetFile, $targetTodos);

                $success_message = "Todo moved successfully!";
            } catch (\Exception $e) {
                $error_message = "Error moving todo: " . $e->getMessage();
            }
        }
    }

    // If AJAX request, return JSON and exit
    if ($isAjax) {
        header('Content-Type: application/json');
        if (!empty($error_message)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $error_message]);
        } else {
            // Generate new CSRF token for next request
            $newToken = $csrfProtect->getToken("todo_form_{$project}");
            echo json_encode(['success' => true, 'message' => $success_message ?? 'Moved', 'csrf_token' => $newToken]);
        }
        exit;
    }
}

// Handle adding new todo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_todo' && $project !== null) {
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!$csrfProtect->validateToken($csrf_token)) {
        $error_message = "Invalid security token. Please try again.";
    } else {
        $newTodoText = trim($_POST['new_todo'] ?? '');

        if (!empty($newTodoText)) {
            try {
                // Read existing todos
                try {
                    $rawContent = $todoReader->readRawContent($username, $currentYear, $project);
                    $existingTodos = $todoReader->parseTodos($rawContent);
                } catch (\Exception $e) {
                    $existingTodos = [];
                }

                // Check if the input has a link (wiki-style [[...]])
                $hasLink = preg_match('/\[\[([^\]]+)\]\]/', $newTodoText, $linkMatches);
                $linkText = '';
                $linkFile = '';

                // Handle link first if present
                $textWithPlaceholder = $newTodoText;
                if ($hasLink) {
                    $linkText = $linkMatches[1];
                    $linkFile = $todoReader->linkTextToFileName($linkText);
                    $textWithPlaceholder = preg_replace('/\[\[([^\]]+)\]\]/', 'LINK_PLACEHOLDER', $newTodoText);
                }

                // Now check for date at the start
                // Pattern matches: [HH:MM:SS ]DD-mon-YYYY description
                $datePattern = '^((?:\d{2}:\d{2}:\d{2}\s+)?\d{2}-[a-z]{3}-\d{4})\s+(.+)';

                if (preg_match("/$datePattern/i", $textWithPlaceholder, $dateMatches)) {
                    // Has a date at the start
                    $createDate = $dateMatches[1];
                    // If no time specified, add default 12:00:00 before the date
                    if (!preg_match('/\d{2}:\d{2}:\d{2}/', $createDate)) {
                        $createDate = '12:00:00 ' . $createDate;
                    }
                    $description = trim($dateMatches[2]);
                    // Replace placeholder with link text if it was there
                    $description = str_replace('LINK_PLACEHOLDER', '[[' . $linkText . ']]', $description);
                } else {
                    // Use current client datetime
                    $clientTimezone = $_POST['client_timezone'] ?? 'UTC';
                    $clientDatetime = $_POST['client_datetime'] ?? date('Y-m-d H:i:s');

                    // Convert client datetime to our format: HH:MM:SS DD-Mon-YYYY
                    try {
                        $dt = new \DateTime($clientDatetime, new \DateTimeZone($clientTimezone));
                        $createDate = $dt->format('H:i:s d-M-Y');
                    } catch (\Exception $e) {
                        // Fallback to server time
                        $createDate = date('H:i:s d-M-Y');
                    }
                    $description = $textWithPlaceholder;
                    // Replace placeholder with link text if it was there
                    $description = str_replace('LINK_PLACEHOLDER', '[[' . $linkText . ']]', $description);
                }

                // Create new todo item
                $newTodo = [
                    'isComplete' => false,
                    'createDate' => $createDate,
                    'description' => $description,
                    'completeDate' => null,
                    'hasLink' => $hasLink,
                    'linkText' => $linkText,
                    'linkFile' => $linkFile,
                ];

                $existingTodos[] = $newTodo;

                // Write back to file
                $todoWriter->writeTodos($username, $currentYear, $project, $existingTodos);
                $success_message = "Todo added successfully!";
            } catch (\Exception $e) {
                $error_message = "Error adding todo: " . $e->getMessage();
            }
        }
    }
}

// Handle form submission to save todos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['todo']) && isset($_POST['todo_data']) && $project !== null) {
    $csrf_token = $_POST['csrf_token'] ?? '';

    // Check if this is an AJAX request
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

    if (!$csrfProtect->validateToken($csrf_token)) {
        $error_message = "Invalid security token. Please try again.";
    } else {
        // Update todo completion status based on form submission
        $todos = [];
        $checked = $_POST['todo'] ?? [];

        foreach ($_POST['todo_data'] as $index => $todoData) {
            // Build todo from form data
            $todo = [
                'description' => $todoData['description'] ?? '',
                'createDate' => $todoData['createDate'] ?? '',
                'completeDate' => $todoData['completeDate'] ?? '',
                'hasLink' => !empty($todoData['hasLink']),
                'linkText' => $todoData['linkText'] ?? '',
                'linkFile' => $todoData['linkFile'] ?? '',
            ];

            // Update completion status
            $todo['isComplete'] = isset($checked[$index]);

            // Update completion date if just completed
            if ($todo['isComplete'] && empty($todoData['completeDate'])) {
                // Use current timestamp for completion
                $clientTimezone = $_POST['client_timezone'] ?? 'UTC';
                $clientDatetime = $_POST['client_datetime'] ?? date('Y-m-d H:i:s');

                try {
                    $dt = new \DateTime($clientDatetime, new \DateTimeZone($clientTimezone));
                    $todo['completeDate'] = $dt->format('H:i:s d-M-Y');
                } catch (\Exception $e) {
                    $todo['completeDate'] = date('H:i:s d-M-Y');
                }
            } elseif (!$todo['isComplete']) {
                $todo['completeDate'] = '';
            }

            $todos[] = $todo;
        }

        // Write back to file
        try {
            $todoWriter->writeTodos($username, $currentYear, $project, $todos);
            $success_message = "Todos updated successfully!";
        } catch (\Exception $e) {
            $error_message = "Error saving todos: " . $e->getMessage();
        }
    }

    // If AJAX request, return JSON and exit
    if ($isAjax) {
        header('Content-Type: application/json');
        if (!empty($error_message)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $error_message]);
        } else {
            // Generate new CSRF token for next request
            $newToken = $csrfProtect->getToken("todo_form_{$project}");
            echo json_encode(['success' => true, 'message' => $success_message ?? 'Saved', 'csrf_token' => $newToken]);
        }
        exit;
    }
}

// Show dashboard if no specific project
if ($project === null) {
    // Show list of projects
    try {
        $projects = $todoReader->listProjects($username, $currentYear);

        $page = new \Template(config: $config);
        $page->setTemplate("todos/dashboard.tpl.php");
        $page->set("username", $username);
        $page->set("year", $currentYear);
        $page->set("projects", $projects);
        $page->set("projectListHtml", $todoRenderer->renderProjectList($projects, $username, $currentYear));

        $inner = $page->grabTheGoods();

        $layout = new \Template(config: $config);
        $layout->setTemplate("layout/admin_base.tpl.php");
        $layout->set("page_title", "My Todos");
        $layout->set("page_content", $inner);
        $layout->set("username", $username);
        $layout->set("site_version", SENTIMENTAL_VERSION);
        $layout->echoToScreen();
        exit;
    } catch (\Exception $e) {
        $error_message = "Error loading projects: " . $e->getMessage();
    }
}

// Show specific project
try {
    // Read the todo file
    $rawContent = $todoReader->readRawContent($username, $currentYear, $project);
    $todos = $todoReader->parseTodos($rawContent);

    // Generate CSRF token for the form
    $csrfToken = $csrfProtect->getToken("todo_form_{$project}");

    // Render the page
    $page = new \Template(config: $config);
    $page->setTemplate("todos/view.tpl.php");
    $page->set("username", $username);
    $page->set("year", $currentYear);
    $page->set("project", $project);
    $page->set("todos", $todos);
    $page->set("error_message", $error_message);
    $page->set("success_message", $success_message);
    $page->set("csrf_token", $csrfToken);
    $page->set("todosHtml", $todoRenderer->renderTodos($todos, $username, $currentYear));
    $page->set("formHtml", $todoRenderer->renderTodoForm($todos, $_SERVER['REQUEST_URI'], $csrfToken, $username, $currentYear));

    $inner = $page->grabTheGoods();

    $layout = new \Template(config: $config);
    $layout->setTemplate("layout/admin_base.tpl.php");
    $layout->set("page_title", "Todo: " . htmlspecialchars($project));
    $layout->set("page_content", $inner);
    $layout->set("username", $username);
    $layout->set("site_version", SENTIMENTAL_VERSION);
    $layout->echoToScreen();

} catch (\Exception $e) {
    // File doesn't exist - show empty page or 404
    $csrfToken = $csrfProtect->getToken("create_project_{$project}");

    $page = new \Template(config: $config);
    $page->setTemplate("todos/404.tpl.php");
    $page->set("username", $username);
    $page->set("project", $project);
    $page->set("year", $currentYear);
    $page->set("error", $e->getMessage());
    $page->set("csrf_token", $csrfToken);

    $inner = $page->grabTheGoods();

    $layout = new \Template(config: $config);
    $layout->setTemplate("layout/admin_base.tpl.php");
    $layout->set("page_title", "Todo Not Found");
    $layout->set("page_content", $inner);
    $layout->set("username", $username);
    $layout->set("site_version", SENTIMENTAL_VERSION);
    $layout->echoToScreen();
}
exit;
