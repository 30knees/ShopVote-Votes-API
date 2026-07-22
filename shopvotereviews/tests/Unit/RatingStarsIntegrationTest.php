<?php

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Tests\Unit;

use PHPUnit\Framework\TestCase;

class RatingStarsIntegrationTest extends TestCase
{
    public function testHeaderHookRendersValidatedRatingStarsTemplate(): void
    {
        $module = file_get_contents(__DIR__ . '/../../shopvotereviews.php');
        $template = file_get_contents(__DIR__ . '/../../views/templates/hook/ratingstars.tpl');
        $loader = file_get_contents(__DIR__ . '/../../views/js/ratingstars.js');

        $this->assertIsString($module);
        $this->assertStringContainsString("CONFIG_KEYS['RATINGSTARS_ENABLED']", $module);
        $this->assertStringContainsString('shopvote.service.ratingstars_parser', $module);
        $this->assertStringContainsString('/views/templates/hook/ratingstars.tpl', $module);
        $this->assertStringContainsString('shopvote-ratingstars-loader', $module);
        $this->assertGreaterThanOrEqual(3, substr_count($module, "'version' => \$this->version"));
        $this->assertIsString($template);
        $this->assertStringContainsString('shopvote_ratingstars_script_url', $template);
        $this->assertStringContainsString('data-shopvote-shop-id', $template);
        $this->assertStringContainsString('shopvote_ratingstars_arguments', $template);
        $this->assertIsString($loader);
        $this->assertStringContainsString("['createRBadge', 'createBadget', 'createVBadge']", $loader);
        $this->assertStringContainsString("script.addEventListener('load'", $loader);
        $this->assertStringContainsString('document.head.appendChild(script)', $loader);
    }

    public function testHeaderRatingIsOneClickableTrustLine(): void
    {
        $module = file_get_contents(__DIR__ . '/../../shopvotereviews.php');
        $template = file_get_contents(__DIR__ . '/../../views/templates/hook/rating_snippet.tpl');
        $stylesheet = file_get_contents(__DIR__ . '/../../views/css/shopvote.css');

        $this->assertIsString($module);
        $this->assertStringContainsString('https://widgets.shopvote.de/view.php?', $module);
        $this->assertStringContainsString("'bn' => 56", $module);
        $this->assertIsString($template);
        $this->assertSame(1, substr_count($template, '<a '));
        $this->assertStringContainsString('shopvote_header_badge_url', $template);
        $this->assertStringContainsString('shopvote-snippet-seal', $template);
        $this->assertStringContainsString('width="50"', $template);
        $this->assertStringContainsString('height="50"', $template);
        $this->assertStringContainsString('shopvote-snippet-max', $template);
        $this->assertStringContainsString('shopvote-snippet-source', $template);
        $this->assertStringContainsString('shopvote_profile_url', $template);
        $this->assertStringContainsString('ShopVote.de', $template);
        $this->assertStringNotContainsString('(ShopVote.de)', $template);
        $this->assertStringContainsString("s='%count% ratings'", $template);
        $this->assertStringNotContainsString('<span class="shopvote-attribution">', $template);
        $this->assertStringNotContainsString('Verified source: ShopVote', $template);
        $this->assertIsString($stylesheet);
        $this->assertStringContainsString('white-space: nowrap !important;', $stylesheet);
        $this->assertStringContainsString('grid-template-columns:', $stylesheet);
        $this->assertStringContainsString(
            '.shopvote-rating-snippet .shopvote-snippet-count',
            $stylesheet
        );
        $this->assertStringContainsString('display: inline-block !important;', $stylesheet);
        $this->assertStringContainsString('color: inherit !important;', $stylesheet);
    }

    public function testHeaderUpgradeClearsCompiledThemeAssets(): void
    {
        $upgrade = file_get_contents(__DIR__ . '/../../upgrade/upgrade-1.2.3.php');

        $this->assertIsString($upgrade);
        $this->assertStringContainsString('Media::clearCache();', $upgrade);
        $this->assertStringContainsString('Tools::clearSmartyCache();', $upgrade);
    }

    public function testStorefrontAttributionHasNoLanguageSpecificSourcePrefix(): void
    {
        $files = array_merge(
            [__DIR__ . '/../../shopvotereviews.php'],
            glob(__DIR__ . '/../../views/templates/hook/*.tpl') ?: []
        );

        foreach ($files as $file) {
            $contents = file_get_contents($file);
            $this->assertIsString($contents);
            $this->assertStringNotContainsString('Que' . 'lle:', $contents, $file);
        }
    }
}
