<?php
/**
 * ShopVote Reviews - ShopVote URL Validator
 *
 * Keeps provider-controlled URLs in the HTTPS shopvote.de trust boundary.
 */

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Security;

class ShopVoteUrlValidator
{
    public static function normalize(?string $url, bool $requireJavaScript = false): ?string
    {
        if ($url === null
            || $url === ''
            || strlen($url) > 2048
            || preg_match('/[\x00-\x20\x7F]/', $url)
            || !filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);
        if ($parts === false
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)
        ) {
            return null;
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        if ($host !== 'shopvote.de' && !str_ends_with($host, '.shopvote.de')) {
            return null;
        }

        if ($requireJavaScript) {
            $path = strtolower($parts['path'] ?? '');
            if (!str_ends_with($path, '.js')) {
                return null;
            }
        }

        return $url;
    }
}
