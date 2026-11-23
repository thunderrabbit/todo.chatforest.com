<?php

const SENTIMENTAL_VERSION = "Admin chip for pending migrations";

# write errors to screen
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/classes/Mlaphp/Autoloader.php';
// create autoloader instance and register the method with SPL
$autoloader = new \Mlaphp\Autoloader();
spl_autoload_register($autoloader->load(...));

$mla_request = new \Mlaphp\Request();


$config = new \Config();

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

// Pending migrations for the menu's Admin chip. Counted again here because
// the list checkaroo() built predates its auto-apply. Admins only.
$admin_count = 0;
if ($is_logged_in->isLoggedIn() && $is_logged_in->isAdmin()) {
    $admin_count = count($dbExistaroo->getPendingMigrations());
}
