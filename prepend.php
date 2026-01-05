<?php

const SENTIMENTAL_VERSION = "Let me edit";

# write errors to screen
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/classes/Mlaphp/Autoloader.php';
// create autoloader instance and register the method with SPL
$autoloader = new \Mlaphp\Autoloader();
spl_autoload_register(array($autoloader, 'load'));

$mla_request = new \Mlaphp\Request();


function print_rob($object, $exit = true)
{
    echo "<pre>";
    if (is_object($object) && method_exists($object, "toArray")) {
        echo "ResultSet => " . print_r($object->toArray(), true);
    } else {
        print_r($object);
    }
    echo "</pre>";
    if ($exit) {
        exit;
    }
}

/**
 * Log debug output to a file in za_rob_logs/yyyy_mm_dd_$suffix.log
 * Similar to print_rob but writes to a log file instead of echoing to screen
 *
 * @param mixed $object The object or data to log
 * @param string $suffix The suffix for the log filename (default: "log")
 * @return void
 */
function log_rob(mixed $object, string $suffix = "logged"): void
{
    $logDir = __DIR__ . '/za_rob_logs';

    // Ensure log directory exists
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    // Create log filename with current date
    $date = date('Y_m_d');
    $logFile = "{$logDir}/{$date}_{$suffix}.log";

    // Prepare log content
    $timestamp = date('Y-m-d H:i:s');
    $logContent = "\n[{$timestamp}]\n";

    if (is_object($object) && method_exists($object, "toArray")) {
        $logContent .= "ResultSet => " . print_r($object->toArray(), true);
    } else {
        $logContent .= print_r($object, true);
    }

    $logContent .= "\n" . str_repeat('-', 80) . "\n";

    // Write to log file
    file_put_contents($logFile, $logContent, FILE_APPEND | LOCK_EX);
}

try {
    $config = new \Config();
} catch (\Exception $e) {
    echo "Couldn't create Config cause " . $e->getMessage();
    exit;
}

$mla_database = \Database\Base::getPDO($config);
// Check if the database exists and is accessible
$dbExistaroo = new \Database\DBExistaroo(
    config: $config,
    pdo: $mla_database,
);

$errors = $dbExistaroo->checkaroo();

$uri_path = $_SERVER['REQUEST_URI'] ?? '';

if (
    !empty($errors)
    && $errors[0] == "YallGotAnyMoreOfThemUsers"
    && $uri_path != "/login/register.php"
) {
    header(header: "Location: /login/register.php");
    exit;
}


if (!empty($errors)) {
    echo "<h1>Database Errors</h1>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>" . htmlspecialchars($error) . "</li>";
    }
    echo "</ul>";
    exit;
}

$is_logged_in = new \Auth\IsLoggedIn($mla_database, $config);
$is_logged_in->checkLogin($mla_request);
