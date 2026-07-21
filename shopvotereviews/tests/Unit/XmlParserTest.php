<?php
/**
 * ShopVote Reviews - XML Parser Unit Tests
 */

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ShopVote\ShopVoteReviews\Api\XmlParser;
use ShopVote\ShopVoteReviews\Api\XmlParseException;
use ShopVote\ShopVoteReviews\Api\ParsedResponse;

class XmlParserTest extends TestCase
{
    private XmlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new XmlParser();
    }

    /**
     * Test parsing ratingstars response
     */
    public function testParseRatingStarsResponse(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<shopvote>
    <shopid>12345</shopid>
    <name>Test Shop</name>
    <profile>https://www.shopvote.de/bewertung/12345</profile>
    <shopurl>https://www.testshop.de</shopurl>
    <last_vote>2025-01-15 14:30:00</last_vote>
    <rating_summary>
        <rating_value>
            <stars>4.5</stars>
            <score>90</score>
            <word>sehr gut</word>
        </rating_value>
        <ratings_count>150</ratings_count>
        <ratings_positive>140</ratings_positive>
        <ratings_neutral>8</ratings_neutral>
        <ratings_negative>2</ratings_negative>
        <comments_count>120</comments_count>
    </rating_summary>
</shopvote>
XML;

        $result = $this->parser->parse($xml);

        $this->assertInstanceOf(ParsedResponse::class, $result);
        $this->assertEquals('12345', $result->shopId);
        $this->assertEquals('Test Shop', $result->shopName);
        $this->assertEquals('https://www.shopvote.de/bewertung/12345', $result->profileUrl);
        $this->assertEquals('https://www.testshop.de', $result->shopUrl);
        $this->assertNotNull($result->lastVote);
        $this->assertEquals('2025-01-15', $result->lastVote->format('Y-m-d'));

        $this->assertTrue($result->hasSummary);
        $this->assertEquals(4.5, $result->ratingValueStars);
        $this->assertEquals(90.0, $result->ratingValueScore);
        $this->assertEquals('sehr gut', $result->ratingWord);
        $this->assertEquals(150, $result->ratingsCount);
        $this->assertEquals(140, $result->ratingsPositive);
        $this->assertEquals(8, $result->ratingsNeutral);
        $this->assertEquals(2, $result->ratingsNegative);
        $this->assertEquals(120, $result->commentsCount);

        $this->assertFalse($result->hasReviews);
        $this->assertEmpty($result->reviews);
    }

    /**
     * Test parsing last25 response
     */
    public function testParseLast25Response(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<shopvote>
    <shopid>12345</shopid>
    <name>Test Shop</name>
    <profile>https://www.shopvote.de/bewertung/12345</profile>
    <reviews>
        <review id="98765" isVerified="true">
            <review_url>https://www.shopvote.de/bewertung/12345#98765</review_url>
            <review_date>2025-01-14 10:30:00</review_date>
            <reviewer>Max M.</reviewer>
            <review_rating>
                <stars>5</stars>
            </review_rating>
            <text>Excellent service and fast delivery!</text>
        </review>
        <review id="98764" isVerified="false">
            <review_url>https://www.shopvote.de/bewertung/12345#98764</review_url>
            <review_date>2025-01-13 15:20:00</review_date>
            <reviewer>Anna S.</reviewer>
            <review_rating>
                <stars>4</stars>
            </review_rating>
            <text>Good quality products.</text>
            <review_answers>
                <answer type="Shop">
                    <date>2025-01-13 16:00:00</date>
                    <text>Thank you for your feedback!</text>
                </answer>
            </review_answers>
        </review>
    </reviews>
</shopvote>
XML;

        $result = $this->parser->parse($xml);

        $this->assertInstanceOf(ParsedResponse::class, $result);
        $this->assertEquals('12345', $result->shopId);
        $this->assertTrue($result->hasReviews);
        $this->assertCount(2, $result->reviews);

        // First review
        $review1 = $result->reviews[0];
        $this->assertEquals('98765', $review1->reviewId);
        $this->assertTrue($review1->isVerified);
        $this->assertEquals('Max M.', $review1->reviewer);
        $this->assertEquals(5.0, $review1->reviewRatingStars);
        $this->assertEquals('Excellent service and fast delivery!', $review1->reviewText);
        $this->assertEmpty($review1->answers);

        // Second review with answer
        $review2 = $result->reviews[1];
        $this->assertEquals('98764', $review2->reviewId);
        $this->assertFalse($review2->isVerified);
        $this->assertEquals('Anna S.', $review2->reviewer);
        $this->assertEquals(4.0, $review2->reviewRatingStars);
        $this->assertCount(1, $review2->answers);

        $answer = $review2->answers[0];
        $this->assertEquals('Shop', $answer->type);
        $this->assertEquals('Thank you for your feedback!', $answer->text);
        $this->assertTrue($answer->isShopResponse());
    }

    /**
     * Test parsing last25ext response (combined)
     */
    public function testParseLast25ExtResponse(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<shopvote>
    <shopid>12345</shopid>
    <name>Test Shop</name>
    <profile>https://www.shopvote.de/bewertung/12345</profile>
    <shopurl>https://www.testshop.de</shopurl>
    <last_vote>2025-01-15 14:30:00</last_vote>
    <rating_summary>
        <rating_value>
            <stars>4.7</stars>
            <score>94</score>
            <word>sehr gut</word>
        </rating_value>
        <ratings_count>200</ratings_count>
        <ratings_positive>190</ratings_positive>
        <ratings_neutral>7</ratings_neutral>
        <ratings_negative>3</ratings_negative>
        <comments_count>180</comments_count>
    </rating_summary>
    <reviews>
        <review id="99999" isVerified="true">
            <review_url>https://www.shopvote.de/bewertung/12345#99999</review_url>
            <review_date>2025-01-15 12:00:00</review_date>
            <reviewer>Test User</reviewer>
            <review_rating>
                <stars>5</stars>
            </review_rating>
            <text>Great shop!</text>
        </review>
    </reviews>
</shopvote>
XML;

        $result = $this->parser->parse($xml);

        // Should have both summary and reviews
        $this->assertTrue($result->hasSummary);
        $this->assertTrue($result->hasReviews);

        // Verify summary
        $this->assertEquals(4.7, $result->ratingValueStars);
        $this->assertEquals(200, $result->ratingsCount);

        // Verify reviews
        $this->assertCount(1, $result->reviews);
        $this->assertEquals('99999', $result->reviews[0]->reviewId);
    }

    /**
     * Test parsing response with missing optional nodes
     */
    public function testParseMissingOptionalNodes(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<shopvote>
    <shopid>12345</shopid>
    <name>Test Shop</name>
</shopvote>
XML;

        $result = $this->parser->parse($xml);

        $this->assertEquals('12345', $result->shopId);
        $this->assertEquals('Test Shop', $result->shopName);
        $this->assertNull($result->profileUrl);
        $this->assertNull($result->shopUrl);
        $this->assertNull($result->lastVote);
        $this->assertFalse($result->hasSummary);
        $this->assertFalse($result->hasReviews);
    }

    /**
     * Test parsing with German decimal format
     */
    public function testParseGermanDecimalFormat(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<shopvote>
    <shopid>12345</shopid>
    <rating_summary>
        <rating_value>
            <stars>4,5</stars>
            <score>90,5</score>
        </rating_value>
        <ratings_count>100</ratings_count>
    </rating_summary>
</shopvote>
XML;

        $result = $this->parser->parse($xml);

        $this->assertEquals(4.5, $result->ratingValueStars);
        $this->assertEquals(90.5, $result->ratingValueScore);
    }

    /**
     * Test parsing invalid XML throws exception
     */
    public function testParseInvalidXmlThrowsException(): void
    {
        $this->expectException(XmlParseException::class);

        $invalidXml = 'This is not valid XML';
        $this->parser->parse($invalidXml);
    }

    /**
     * Test parsing malformed XML throws exception
     */
    public function testParseMalformedXmlThrowsException(): void
    {
        $this->expectException(XmlParseException::class);

        $malformedXml = '<?xml version="1.0"?><shopvote><unclosed>';
        $this->parser->parse($malformedXml);
    }

    /**
     * Test parsing empty response
     */
    public function testParseEmptyResponse(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<shopvote>
</shopvote>
XML;

        $result = $this->parser->parse($xml);

        $this->assertNull($result->shopId);
        $this->assertFalse($result->hasSummary);
        $this->assertFalse($result->hasReviews);
        $this->assertFalse($result->hasData());
    }

    /**
     * Test parsing review with multiple answers
     */
    public function testParseReviewWithMultipleAnswers(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<shopvote>
    <shopid>12345</shopid>
    <reviews>
        <review id="11111">
            <reviewer>Customer</reviewer>
            <review_rating><stars>3</stars></review_rating>
            <text>Could be better.</text>
            <review_answers>
                <answer type="Shop">
                    <date>2025-01-10</date>
                    <text>We apologize for the inconvenience.</text>
                </answer>
                <answer type="Kunde">
                    <date>2025-01-11</date>
                    <text>Thank you for responding.</text>
                </answer>
                <answer type="Shop">
                    <date>2025-01-12</date>
                    <text>Please contact us for a resolution.</text>
                </answer>
            </review_answers>
        </review>
    </reviews>
</shopvote>
XML;

        $result = $this->parser->parse($xml);

        $this->assertCount(1, $result->reviews);
        $review = $result->reviews[0];

        $this->assertCount(3, $review->answers);
        $this->assertTrue($review->hasAnswers());

        $this->assertTrue($review->answers[0]->isShopResponse());
        $this->assertTrue($review->answers[1]->isCustomerResponse());
        $this->assertTrue($review->answers[2]->isShopResponse());
    }

    /**
     * Test parsing different date formats
     */
    public function testParseDifferentDateFormats(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<shopvote>
    <shopid>12345</shopid>
    <last_vote>2025-01-15T14:30:00</last_vote>
    <reviews>
        <review id="1">
            <review_date>15.01.2025 10:30:00</review_date>
            <review_rating><stars>5</stars></review_rating>
            <text>Good</text>
        </review>
    </reviews>
</shopvote>
XML;

        $result = $this->parser->parse($xml);

        $this->assertNotNull($result->lastVote);
        $this->assertEquals('2025-01-15', $result->lastVote->format('Y-m-d'));

        $this->assertNotNull($result->reviews[0]->reviewDate);
        $this->assertEquals('2025-01-15', $result->reviews[0]->reviewDate->format('Y-m-d'));
    }

    /**
     * Test hasData method
     */
    public function testHasData(): void
    {
        // With shop ID
        $xmlWithShopId = '<?xml version="1.0"?><shopvote><shopid>123</shopid></shopvote>';
        $result1 = $this->parser->parse($xmlWithShopId);
        $this->assertTrue($result1->hasData());

        // With summary
        $xmlWithSummary = '<?xml version="1.0"?><shopvote><rating_summary><rating_value><stars>4</stars></rating_value><ratings_count>10</ratings_count></rating_summary></shopvote>';
        $result2 = $this->parser->parse($xmlWithSummary);
        $this->assertTrue($result2->hasData());

        // With reviews
        $xmlWithReviews = '<?xml version="1.0"?><shopvote><reviews><review id="1"><text>Test</text></review></reviews></shopvote>';
        $result3 = $this->parser->parse($xmlWithReviews);
        $this->assertTrue($result3->hasData());
    }

    /**
     * Test getReviewCount method
     */
    public function testGetReviewCount(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<shopvote>
    <reviews>
        <review id="1"><text>Review 1</text></review>
        <review id="2"><text>Review 2</text></review>
        <review id="3"><text>Review 3</text></review>
    </reviews>
</shopvote>
XML;

        $result = $this->parser->parse($xml);
        $this->assertEquals(3, $result->getReviewCount());
    }

    public function testRejectsDocumentType(): void
    {
        $this->expectException(XmlParseException::class);
        $this->parser->parse('<!DOCTYPE shopvote [<!ENTITY test "value">]><shopvote>&test;</shopvote>');
    }

    public function testRejectsOversizedXml(): void
    {
        $this->expectException(XmlParseException::class);
        $this->parser->parse('<shopvote>' . str_repeat('a', 2097152) . '</shopvote>');
    }

    public function testEnforcesRatingAndCountBounds(): void
    {
        $result = $this->parser->parse(
            '<shopvote><rating_summary><rating_value><stars>9</stars><score>101</score></rating_value>' .
            '<ratings_count>-1</ratings_count></rating_summary></shopvote>'
        );

        $this->assertNull($result->ratingValueStars);
        $this->assertNull($result->ratingValueScore);
        $this->assertNull($result->ratingsCount);
    }

    public function testDropsUntrustedExternalUrls(): void
    {
        $result = $this->parser->parse(
            '<shopvote><profile>javascript:alert(1)</profile><reviews><review id="1">' .
            '<review_url>https://evil.example/review</review_url></review></reviews></shopvote>'
        );

        $this->assertNull($result->profileUrl);
        $this->assertNull($result->reviews[0]->reviewUrl);
    }
}
