<?php

# Must include here because DH runs FastCGI https://www.phind.com/search?cache=zfj8o8igbqvaj8cm91wp1b7k
# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

/**
 * @var \Auth\IsLoggedIn $is_logged_in
 * @var \Config $config
 * @var int $admin_count
 */

if ($is_logged_in->isLoggedIn() && $is_logged_in->isAdmin()) {
    $page = new \Template($config, admin_count: $admin_count);
    $page->setTemplate("admin/workers/index.tpl.php");
    $inner = $page->grabTheGoods();

    $layout = new \Template($config, admin_count: $admin_count);
    $layout->setTemplate("layout/admin_base.tpl.php");
    $layout->set("page_title", "Workers");
    $layout->set("page_content", $inner);
    $layout->echoToScreen();
    exit;
} else {
    header("Location: /login/");
    exit;
}
