<?php

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Tests\Unit;

use PHPUnit\Framework\TestCase;

class AdminConfigurationTemplateTest extends TestCase
{
    public function testEasyReviewsFieldsRenderStoredCodeSafely(): void
    {
        $template = file_get_contents(__DIR__ . '/../../views/templates/admin/configuration.html.twig');

        $this->assertIsString($template);
        $this->assertStringContainsString("{{ config.EASYREVIEWS_HTML_CODE|e('html') }}", $template);
        $this->assertStringContainsString("{{ config.EASYREVIEWS_JAVASCRIPT_CODE|e('html') }}", $template);
    }

    public function testSwitchRowsReserveSpaceBeforeHelpText(): void
    {
        $template = file_get_contents(__DIR__ . '/../../views/templates/admin/configuration.html.twig');

        $this->assertIsString($template);
        $this->assertStringContainsString('.shopvote-configuration .form-check.form-switch', $template);
        $this->assertStringContainsString('min-height: 1.5rem;', $template);
    }

    public function testFloatingBadgeFieldRendersStoredCodeSafely(): void
    {
        $template = file_get_contents(__DIR__ . '/../../views/templates/admin/configuration.html.twig');

        $this->assertIsString($template);
        $this->assertStringContainsString('name="ratingstars_enabled"', $template);
        $this->assertStringContainsString('name="ratingstars_code"', $template);
        $this->assertStringContainsString("{{ config.RATINGSTARS_CODE|e('html') }}", $template);
    }

    public function testSidebarSettingIsExplicitlyDescribedAsAThemeColumn(): void
    {
        $template = file_get_contents(__DIR__ . '/../../views/templates/admin/configuration.html.twig');

        $this->assertIsString($template);
        $this->assertStringContainsString('Display in Theme Side Column', $template);
        $this->assertStringNotContainsString('Display in Sidebar', $template);
    }
}
