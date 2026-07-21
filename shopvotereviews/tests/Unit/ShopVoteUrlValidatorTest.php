<?php

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ShopVote\ShopVoteReviews\Security\ShopVoteUrlValidator;

class ShopVoteUrlValidatorTest extends TestCase
{
    public function testAcceptsHttpsShopVoteHosts(): void
    {
        $this->assertSame(
            'https://feedback.shopvote.de/srt-v4.min.js',
            ShopVoteUrlValidator::normalize('https://feedback.shopvote.de/srt-v4.min.js', true)
        );
    }

    /** @dataProvider invalidUrlProvider */
    public function testRejectsUntrustedUrls(string $url): void
    {
        $this->assertNull(ShopVoteUrlValidator::normalize($url));
    }

    public static function invalidUrlProvider(): array
    {
        return [
            ['javascript:alert(1)'],
            ['http://www.shopvote.de/review/1'],
            ['https://shopvote.de.evil.example/review/1'],
            ['https://user@shopvote.de/review/1'],
            ['https://shopvote.de:444/review/1'],
        ];
    }
}
