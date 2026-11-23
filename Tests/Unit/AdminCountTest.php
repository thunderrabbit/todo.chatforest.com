<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;

/**
 * The Admin link's pending-migration chip, drawn by layout/admin_base.tpl.php
 * from the count the Template was constructed with.
 */
class AdminCountTest extends Unit
{
    private function renderMenu(int $count, bool $overrideWith99 = false): string
    {
        $config = new \Config();
        $config->app_path = dirname(__DIR__, 2);

        $layout = new \Template($config, admin_count: $count);
        $layout->setTemplate('layout/admin_base.tpl.php');
        $layout->set('page_content', '');
        if ($overrideWith99) {
            $layout->set('admin_count', 99);
        }

        return $layout->grabTheGoods();
    }

    public function testSetCannotReplaceTheConstructorsCount(): void
    {
        $html = $this->renderMenu(3, true);
        $this->assertStringContainsString('<span class="AdminCount" aria-hidden="true">3</span>', $html);
        $this->assertStringNotContainsString('99', $html);
    }

    public function testAdminWithPendingMigrationsSeesChipAndLabel(): void
    {
        $html = $this->renderMenu(3);
        $this->assertStringContainsString('class="AdminCount"', $html);
        $this->assertStringContainsString('aria-label="Admin Site, 3 waiting for an admin"', $html);
    }

    public function testAdminWithNothingPendingSeesThePlainLink(): void
    {
        $html = $this->renderMenu(0);
        $this->assertStringContainsString('<a href="/admin/">Admin Site</a>', $html);
        $this->assertStringNotContainsString('AdminCount', $html);
    }

    public function testPlainUserAndLoggedOutVisitorNeverSeeACount(): void
    {
        // prepend.php hands both of them 0: only an admin is counted.
        $html = $this->renderMenu(0);
        $this->assertStringNotContainsString('AdminCount', $html);
        $this->assertStringNotContainsString('waiting for an admin', $html);
    }
}
