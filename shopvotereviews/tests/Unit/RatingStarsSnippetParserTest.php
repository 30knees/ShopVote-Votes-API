<?php

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ShopVote\ShopVoteReviews\Service\RatingStarsSnippetParser;

class RatingStarsSnippetParserTest extends TestCase
{
    public function testParsesCurrentOfficialDeferredLoadWrapper(): void
    {
        $snippet = <<<'HTML'
<script src="https://widgets.shopvote.de/js/reputation-badge-v2.min.js" defer></script>
<script>
window.addEventListener?window.addEventListener("load",loadBadge,!1):window.attachEvent&&window.attachEvent("onload",loadBadge);
function loadBadge(){
var myShopID = 26444; var myBadgetType = 1; var myLanguage = 'DE';
var mySrc = ('https:' === document.location.protocol ? 'https' : 'http');
createRBadge(myShopID, myBadgetType, mySrc);}
</script>
HTML;

        $parsed = (new RatingStarsSnippetParser())->parse($snippet);

        $this->assertSame('https://widgets.shopvote.de/js/reputation-badge-v2.min.js', $parsed['script_url']);
        $this->assertSame('createRBadge', $parsed['function']);
        $this->assertSame(26444, $parsed['shop_id']);
        $this->assertSame(1, $parsed['badge_type']);
        $this->assertSame('DE', $parsed['language']);
        $this->assertNull($parsed['z_index']);
        $this->assertSame([], $parsed['arguments']);
    }

    public function testParsesOfficialReputationBadgeSnippetIntoCanonicalCode(): void
    {
        $snippet = <<<'HTML'
<script src="https://widgets.shopvote.de/js/reputation-badge-v2.min.js"></script>
<script>
var myShopID = 26444;
var myBadgetType = 1;
var myLanguage = 'DE';
var myZIndex = 9999;
var mySrc = ('https:' === document.location.protocol ? 'https' : 'http');
createRBadge(myShopID, myBadgetType, mySrc);
</script>
HTML;

        $parsed = (new RatingStarsSnippetParser())->parse($snippet);

        $this->assertSame('https://widgets.shopvote.de/js/reputation-badge-v2.min.js', $parsed['script_url']);
        $this->assertSame('createRBadge', $parsed['function']);
        $this->assertSame(26444, $parsed['shop_id']);
        $this->assertSame(1, $parsed['badge_type']);
        $this->assertSame('DE', $parsed['language']);
        $this->assertSame(9999, $parsed['z_index']);
        $this->assertSame([], $parsed['arguments']);
        $this->assertSame(
            <<<'JS'
var myShopID = 26444;
var myBadgetType = 1;
var myLanguage = "DE";
var myZIndex = 9999;
var mySrc = "https";
createRBadge(myShopID, myBadgetType, mySrc);
JS,
            $parsed['initializer']
        );
    }

    public function testSupportsOfficialPositionAndMobileWidthArguments(): void
    {
        $snippet = <<<'HTML'
<script src="https://widgets.shopvote.de/js/badget-98x98.min.js"></script>
<script>
var myShopID = '26444';
var myBadgetType = 1;
var mySrc = ('https:' == document.location.protocol ? 'https' : 'http');
createBadget(myShopID, myBadgetType, mySrc, 20, 30, 'right', 'bottom', 768);
</script>
HTML;

        $parsed = (new RatingStarsSnippetParser())->parse($snippet);

        $this->assertStringContainsString('var myShopID = 26444;', $parsed['initializer']);
        $this->assertSame([20, 30, 'right', 'bottom', 768], $parsed['arguments']);
        $this->assertStringContainsString(
            'createBadget(myShopID, myBadgetType, mySrc, 20, 30, "right", "bottom", 768);',
            $parsed['initializer']
        );
    }

    public function testRejectsThirdPartyScriptUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('widgets.shopvote.de');

        (new RatingStarsSnippetParser())->parse(
            '<script src="https://evil.example/badge.js"></script>'
            . '<script>var myShopID=26444;var myBadgetType=1;var mySrc="https";'
            . 'createRBadge(myShopID,myBadgetType,mySrc);</script>'
        );
    }

    public function testRejectsArbitraryInlineJavascript(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unsupported JavaScript');

        (new RatingStarsSnippetParser())->parse(
            '<script src="https://widgets.shopvote.de/js/reputation-badge-v2.min.js"></script>'
            . '<script>var myShopID=26444;var myBadgetType=1;var mySrc="https";'
            . 'fetch("https://evil.example");createRBadge(myShopID,myBadgetType,mySrc);</script>'
        );
    }

    public function testRejectsArbitraryJavascriptInsideOfficialLoadWrapper(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unsupported JavaScript');

        (new RatingStarsSnippetParser())->parse(
            '<script src="https://widgets.shopvote.de/js/reputation-badge-v2.min.js" defer></script>'
            . '<script>window.addEventListener?window.addEventListener("load",loadBadge,!1):'
            . 'window.attachEvent&&window.attachEvent("onload",loadBadge);function loadBadge(){'
            . 'var myShopID=26444;var myBadgetType=1;var mySrc="https";fetch("https://evil.example");'
            . 'createRBadge(myShopID,myBadgetType,mySrc);}</script>'
        );
    }

    public function testRejectsOtherExternalScriptAttributes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unsupported attributes');

        (new RatingStarsSnippetParser())->parse(
            '<script src="https://widgets.shopvote.de/js/reputation-badge-v2.min.js" defer async></script>'
            . '<script>var myShopID=26444;var myBadgetType=1;var mySrc="https";'
            . 'createRBadge(myShopID,myBadgetType,mySrc);</script>'
        );
    }

    public function testRejectsUnsupportedBadgeArguments(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new RatingStarsSnippetParser())->parse(
            '<script src="https://widgets.shopvote.de/js/badget-98x98.min.js"></script>'
            . '<script>var myShopID=26444;var myBadgetType=1;var mySrc="https";'
            . 'createBadget(myShopID,myBadgetType,mySrc,alert(1));</script>'
        );
    }
}
