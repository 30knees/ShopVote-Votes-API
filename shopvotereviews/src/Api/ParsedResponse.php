<?php
/**
 * ShopVote Reviews - Parsed Response
 *
 * Data structure for parsed API response.
 */

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Api;

class ParsedResponse
{
    // Shop profile data
    public ?string $shopId = null;
    public ?string $shopName = null;
    public ?string $profileUrl = null;
    public ?string $shopUrl = null;
    public ?\DateTime $lastVote = null;

    // Rating summary data
    public bool $hasSummary = false;
    public ?float $ratingValueStars = null;
    public ?float $ratingValueScore = null;
    public ?string $ratingWord = null;
    public ?int $ratingsCount = null;
    public ?int $ratingsPositive = null;
    public ?int $ratingsNeutral = null;
    public ?int $ratingsNegative = null;
    public ?int $commentsCount = null;

    // Reviews
    public bool $hasReviews = false;
    /** @var ParsedReview[] */
    public array $reviews = [];

    /**
     * Get the number of reviews parsed
     */
    public function getReviewCount(): int
    {
        return count($this->reviews);
    }

    /**
     * Check if this is a valid response with any useful data
     */
    public function hasData(): bool
    {
        return $this->hasSummary || $this->hasReviews || $this->shopId !== null;
    }
}
