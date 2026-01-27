<?php
/**
 * ShopVote Reviews - Sync Result
 *
 * Result object for sync operations.
 */

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Service;

class SyncResult
{
    /** @var bool Whether the sync was successful */
    public bool $success = false;

    /** @var string|null Error message if failed */
    public ?string $error = null;

    /** @var int|null HTTP status code from API */
    public ?int $httpCode = null;

    /** @var bool Whether the sync was skipped (rate limit) */
    public bool $skipped = false;

    /** @var bool Whether another sync is in progress */
    public bool $locked = false;

    /** @var bool Whether we should try fallback methods */
    public bool $shouldFallback = false;

    /** @var bool Whether summary data was received */
    public bool $hasSummary = false;

    /** @var int Number of reviews updated */
    public int $reviewsUpdated = 0;

    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'error' => $this->error,
            'http_code' => $this->httpCode,
            'skipped' => $this->skipped,
            'locked' => $this->locked,
            'has_summary' => $this->hasSummary,
            'reviews_updated' => $this->reviewsUpdated,
        ];
    }
}
