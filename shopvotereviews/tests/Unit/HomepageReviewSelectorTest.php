<?php

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ShopVote\ShopVoteReviews\Support\HomepageReviewSelector;

class HomepageReviewSelectorTest extends TestCase
{
    public function testItPrefersVerifiedMeaningfulUniqueReviews(): void
    {
        $reviews = [
            [
                'review_id' => 'latest-placeholder',
                'review_text' => 'k.A.',
                'review_text_excerpt' => 'k.A.',
                'is_verified' => 1,
            ],
            [
                'review_id' => 'unverified-duplicate',
                'review_text' => 'Sehr gute Qualität und eine ausgesprochen schnelle Lieferung.',
                'review_text_excerpt' => 'Sehr gute Qualität und eine ausgesprochen schnelle Lieferung.',
                'is_verified' => 0,
            ],
            [
                'review_id' => 'verified-duplicate',
                'review_text' => "Sehr gute Qualität und eine ausgesprochen\n schnelle Lieferung.",
                'review_text_excerpt' => "Sehr gute Qualität und eine ausgesprochen\n schnelle Lieferung.",
                'is_verified' => 1,
            ],
            [
                'review_id' => 'verified-second',
                'review_text' => 'Sehr herzliche und kompetente Beratung, tolle Produkte und super lecker.',
                'review_text_excerpt' => 'Sehr herzliche und kompetente Beratung, tolle Produkte und super lecker.',
                'is_verified' => 1,
            ],
            [
                'review_id' => 'unverified-fallback',
                'review_text' => 'Alles kam schnell und sicher verpackt bei mir an.',
                'review_text_excerpt' => 'Alles kam schnell und sicher verpackt bei mir an.',
                'is_verified' => 0,
            ],
        ];

        $selected = HomepageReviewSelector::select($reviews, 2);

        $this->assertSame(['verified-duplicate', 'verified-second'], array_column($selected, 'review_id'));
        $this->assertSame(
            'Sehr gute Qualität und eine ausgesprochen schnelle Lieferung.',
            $selected[0]['review_text_excerpt']
        );
    }

    public function testItFallsBackToNewestNonPlaceholderReviews(): void
    {
        $reviews = [
            ['review_id' => 'empty', 'review_text' => 'N/A', 'is_verified' => 0],
            ['review_id' => 'first', 'review_text' => 'The order arrived quickly and everything was packed carefully.', 'is_verified' => 0],
            ['review_id' => 'second', 'review_text' => 'Friendly communication and very good product quality.', 'is_verified' => 0],
            ['review_id' => 'third', 'review_text' => 'A third usable review that should be outside the limit.', 'is_verified' => 0],
        ];

        $selected = HomepageReviewSelector::select($reviews, 2);

        $this->assertSame(['first', 'second'], array_column($selected, 'review_id'));
    }
}
