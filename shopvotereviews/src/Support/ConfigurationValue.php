<?php

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Support;

class ConfigurationValue
{
    public static function integer($value, int $default): int
    {
        return $value === false || $value === '' ? $default : (int) $value;
    }
}
