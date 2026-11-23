<?php

# Must include here because DH runs FastCGI https://www.phind.com/search?cache=zfj8o8igbqvaj8cm91wp1b7k
# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

/**
 * @var \Auth\IsLoggedIn $is_logged_in
 * @var \Config $config
 * @var int $admin_count
 * @var \Database\DBExistaroo $dbExistaroo
 */

if ($is_logged_in->isLoggedIn() && $is_logged_in->isAdmin()) {
    $page = new \Template(config: $config, admin_count: $admin_count);
    $page->setTemplate("admin/index.tpl.php");
    $page->set(name: "site_version", value: SENTIMENTAL_VERSION);
    $page->set(name: "username", value: $is_logged_in->getLoggedInUsername());

    $pending = $dbExistaroo->getPendingMigrations();
    $page->set(name: "has_pending_migrations", value: !empty($pending));
    $inner = $page->grabTheGoods();

    $layout = new \Template(config: $config, admin_count: $admin_count);
    $layout->setTemplate("layout/admin_base.tpl.php");
    $layout->set("page_title", "Dashboard");
    $layout->set("page_content", $inner);
    $layout->echoToScreen();
    exit;
} else {
    header(header: "Location: /login/");
    exit;
}
