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
                $datePattern = '^(\d{2}-[a-z]{3}-\d{4})\s+(.+)';

                if (preg_match("/$datePattern/i", $textWithPlaceholder, $dateMatches)) {
                    // Has a date at the start
                    $createDate = $dateMatches[1];
                    $description = trim($dateMatches[2]);
                    // Replace placeholder with link text if it was there
                    $description = str_replace('LINK_PLACEHOLDER', $linkText, $description);
                } else {
                    // Use today's date
                    $createDate = date('d-M-Y');
                    $description = $textWithPlaceholder;
                    // Replace placeholder with link text if it was there
                    $description = str_replace('LINK_PLACEHOLDER', $linkText, $description);
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

    if (!$csrfProtect->validateToken($csrf_token)) {
        $error_message = "Invalid security token. Please try again.";
    } else {
        // Read existing todos
        try {
            $rawContent = $todoReader->readRawContent($username, $currentYear, $project);
            $existingTodos = $todoReader->parseTodos($rawContent);
        } catch (\Exception $e) {
            $existingTodos = [];
        }

        // Update todo completion status based on form submission
        $todos = [];
        $checked = $_POST['todo'] ?? [];

        foreach ($_POST['todo_data'] as $index => $todoData) {
            $todo = $existingTodos[$index] ?? null;
            if ($todo) {
                // Update completion status
                $todo['isComplete'] = isset($checked[$index]);

                // Update completion date if just completed
                if ($todo['isComplete'] && empty($existingTodos[$index]['isComplete'])) {
                    $todo['completeDate'] = date('d-M-Y');
                } elseif (!$todo['isComplete']) {
                    $todo['completeDate'] = null;
                }

                $todos[] = $todo;
            }
        }

        // Write back to file
        try {
            $todoWriter->writeTodos($username, $currentYear, $project, $todos);
            $success_message = "Todos updated successfully!";
        } catch (\Exception $e) {
            $error_message = "Error saving todos: " . $e->getMessage();
        }
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
