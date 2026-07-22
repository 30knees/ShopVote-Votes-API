<?php

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Tests\Unit;

use PHPUnit\Framework\TestCase;

class HomepageReviewsStripTest extends TestCase
{
    public function testHomepageUsesCompactStripAndDedicatedPageKeepsFullBlock(): void
    {
        $module = file_get_contents(__DIR__ . '/../../shopvotereviews.php');
        $installer = file_get_contents(__DIR__ . '/../../src/Install/Installer.php');
        $upgrade = file_get_contents(__DIR__ . '/../../upgrade/upgrade-1.3.0.php');
        $strip = file_get_contents(__DIR__ . '/../../views/templates/hook/reviews_strip.tpl');
        $block = file_get_contents(__DIR__ . '/../../views/templates/hook/reviews_block.tpl');

        $this->assertIsString($module);
        $this->assertStringContainsString("renderWidget('reviews_strip'", $module);
        $this->assertStringContainsString("'reviews_strip' => 'views/templates/hook/reviews_strip.tpl'", $module);
        $this->assertStringContainsString('HomepageReviewSelector::select', $module);
        $this->assertIsString($installer);
        $this->assertStringContainsString('positionHomepageFirst', $installer);
        $this->assertIsString($upgrade);
        $this->assertStringContainsString('updatePosition', $upgrade);
        $this->assertStringContainsString('Media::clearCache();', $upgrade);
        $this->assertStringContainsString('Tools::clearSmartyCache();', $upgrade);
        $this->assertIsString($strip);
        $this->assertStringContainsString('shopvote-home-strip', $strip);
        $this->assertStringContainsString('shopvote-home-strip-reviews', $strip);
        $this->assertStringContainsString('shopvote_reviews_url', $strip);
        $this->assertStringContainsString('shopvote_profile_url', $strip);
        $this->assertIsString($block);
        $this->assertStringContainsString("\$shopvote_placement != 'reviews_page'", $block);
    }

    public function testStripHasExplicitResponsiveLayouts(): void
    {
        $stylesheet = file_get_contents(__DIR__ . '/../../views/css/shopvote.css');

        $this->assertIsString($stylesheet);
        $this->assertStringContainsString('.shopvote-home-strip {', $stylesheet);
        $this->assertStringContainsString('@media (min-width: 768px)', $stylesheet);
        $this->assertStringContainsString('@media (min-width: 1200px)', $stylesheet);
        $this->assertStringContainsString('@media (max-width: 767px)', $stylesheet);
        $this->assertStringContainsString('.shopvote-home-strip-review:nth-child(n + 2)', $stylesheet);
        $this->assertStringContainsString('overflow-wrap: anywhere;', $stylesheet);
    }
}
