<?php
/**
 * ShopVote Reviews - Parsed Review
 *
 * Data structure for a parsed review.
 */

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Api;

class ParsedReview
{
    public ?string $reviewId = null;
    public ?string $reviewUrl = null;
    public ?\DateTime $reviewDate = null;
    public ?string $reviewer = null;
    public ?float $reviewRatingStars = null;
    public ?string $reviewText = null;
    public bool $isVerified = false;

    /** @var ParsedAnswer[] */
    public array $answers = [];

    /**
     * Check if this review has answers
     */
    public function hasAnswers(): bool
    {
        return count($this->answers) > 0;
    }
}
