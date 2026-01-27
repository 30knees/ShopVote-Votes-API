<?php
/**
 * ShopVote Reviews - Parsed Answer
 *
 * Data structure for a parsed review answer.
 */

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Api;

class ParsedAnswer
{
    public ?string $type = null;
    public ?\DateTime $date = null;
    public ?string $text = null;

    /**
     * Check if this is a shop response
     */
    public function isShopResponse(): bool
    {
        return strtolower($this->type ?? '') === 'shop';
    }

    /**
     * Check if this is a customer response
     */
    public function isCustomerResponse(): bool
    {
        return strtolower($this->type ?? '') === 'kunde';
    }
}
