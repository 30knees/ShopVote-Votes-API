<?php

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ShopVote\ShopVoteReviews\Service\EasyReviewsSnippetParser;

class EasyReviewsSnippetParserTest extends TestCase
{
    public function testExtractsOnlySupportedValues(): void
    {
        $snippet = <<<'HTML'
<script src="https://feedback.shopvote.de/srt-v4.min.js"></script>
<script>var myToken = "abcDEF_123456"; var myLanguage = "DE"; loadSRT(myToken, "https");</script>
HTML;

        $parsed = (new EasyReviewsSnippetParser())->parse($snippet);

        $this->assertSame('https://feedback.shopvote.de/srt-v4.min.js', $parsed['script_url']);
        $this->assertSame('abcDEF_123456', $parsed['token']);
        $this->assertSame(['language' => 'de'], $parsed['options']);
    }

    public function testExtractsValuesFromSeparateHtmlAndJavaScriptCode(): void
    {
        $html = '<div><span>CUSTOMERMAIL</span><span>ORDERNUMBER</span></div>';
        $javascript = <<<'HTML'
<script src="https://feedback.shopvote.de/srt-v4.min.js"></script>
<script>var myToken = "abcDEF_123456"; var myLanguage = "DE"; loadSRT(myToken, "https");</script>
HTML;

        $parsed = (new EasyReviewsSnippetParser())->parseSnippets($html, $javascript);

        $this->assertSame('https://feedback.shopvote.de/srt-v4.min.js', $parsed['script_url']);
        $this->assertSame('abcDEF_123456', $parsed['token']);
        $this->assertSame(['language' => 'de'], $parsed['options']);
    }

    public function testRequiresBothEasyReviewsCodeParts(): void
    {
        $parser = new EasyReviewsSnippetParser();

        try {
            $parser->parseSnippets('', '<script></script>');
            $this->fail('Missing HTML code should be rejected.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('The EasyReviews HTML code is required.', $exception->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The EasyReviews JavaScript code is required.');
        $parser->parseSnippets('<div></div>', '');
    }

    public function testRejectsThirdPartyScript(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new EasyReviewsSnippetParser())->parse(
            '<script src="https://evil.example/srt.js"></script><script>var myToken="abcDEF_123456";</script>'
        );
    }
}
