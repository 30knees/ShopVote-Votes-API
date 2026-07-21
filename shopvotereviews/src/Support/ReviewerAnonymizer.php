<?php

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Support;

class ReviewerAnonymizer
{
    public static function anonymize(?string $name, string $anonymousLabel = 'Anonymous'): string
    {
        if ($name === null || trim($name) === '') {
            return $anonymousLabel;
        }

        $parts = preg_split('/\s+/u', trim($name));
        $first = $parts[0] ?? '';
        $length = mb_strlen($first, 'UTF-8');

        return $length > 1
            ? mb_substr($first, 0, 1, 'UTF-8') . str_repeat('*', $length - 1)
            : $first . '.';
    }
}
