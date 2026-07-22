<?php

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Tests\Unit;

use PHPUnit\Framework\TestCase;

class SidebarHookCoverageTest extends TestCase
{
    public function testRegistersAndImplementsProductSidebarHooks(): void
    {
        $installer = file_get_contents(__DIR__ . '/../../src/Install/Installer.php');
        $module = file_get_contents(__DIR__ . '/../../shopvotereviews.php');

        $this->assertIsString($installer);
        $this->assertIsString($module);
        $this->assertStringContainsString("'displayLeftColumnProduct'", $installer);
        $this->assertStringContainsString("'displayRightColumnProduct'", $installer);
        $this->assertStringContainsString('function hookDisplayLeftColumnProduct', $module);
        $this->assertStringContainsString('function hookDisplayRightColumnProduct', $module);
    }
}
