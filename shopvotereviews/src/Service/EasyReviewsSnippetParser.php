<?php
/**
 * Parses merchant-supplied ShopVote EasyReviews snippets into inert settings.
 */

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Service;

use ShopVote\ShopVoteReviews\Security\ShopVoteUrlValidator;

class EasyReviewsSnippetParser
{
    /**
     * @return array{script_url:string, token:string, options:array}
     */
    public function parse(string $snippet): array
    {
        if (strlen($snippet) > 32768) {
            throw new \InvalidArgumentException('The EasyReviews code is too large.');
        }

        if (!preg_match_all('/<script\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1[^>]*>/is', $snippet, $matches)) {
            throw new \InvalidArgumentException('No ShopVote script URL was found.');
        }

        $scriptUrl = null;
        foreach ($matches[2] as $candidate) {
            $scriptUrl = ShopVoteUrlValidator::normalize(html_entity_decode(trim($candidate), ENT_QUOTES), true);
            if ($scriptUrl !== null) {
                break;
            }
        }

        if ($scriptUrl === null) {
            throw new \InvalidArgumentException('The script URL must be HTTPS and hosted by ShopVote.');
        }

        $token = null;
        foreach ([
            '/\bdata-token\s*=\s*(["\'])([A-Za-z0-9_-]{8,256})\1/i',
            '/\b(?:myToken|token|shopvoteToken)\s*[:=]\s*(["\'])([A-Za-z0-9_-]{8,256})\1/i',
            '/\bloadSRT\s*\(\s*(["\'])([A-Za-z0-9_-]{8,256})\1/i',
        ] as $pattern) {
            if (preg_match($pattern, $snippet, $tokenMatch)) {
                $token = $tokenMatch[2];
                break;
            }
        }

        if ($token === null) {
            throw new \InvalidArgumentException('No supported EasyReviews token was found.');
        }

        $options = [];
        if (preg_match('/\b(?:language|lang)\s*[:=]\s*(["\'])([a-z]{2})\1/i', $snippet, $match)) {
            $options['language'] = strtolower($match[2]);
        }
        if (preg_match('/\b(?:delay|days)\s*[:=]\s*(\d{1,3})\b/i', $snippet, $match)) {
            $delay = (int) $match[1];
            if (in_array($delay, [4, 7, 14, 30, 40, 60], true)) {
                $options['delay'] = $delay;
            }
        }

        return [
            'script_url' => $scriptUrl,
            'token' => $token,
            'options' => $options,
        ];
    }
}
