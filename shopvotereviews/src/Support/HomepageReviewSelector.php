<?php

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Support;

class HomepageReviewSelector
{
    /**
     * Prefer verified reviews while retaining newest-first order within each group.
     */
    public static function select(array $reviews, int $limit = 2): array
    {
        $limit = max(0, min(25, $limit));
        if ($limit === 0) {
            return [];
        }

        $candidates = [];
        foreach ($reviews as $position => $review) {
            $text = self::normalizeText((string) ($review['review_text'] ?? $review['review_text_excerpt'] ?? ''));
            if (!self::isMeaningful($text)) {
                continue;
            }

            $excerpt = self::normalizeText((string) ($review['review_text_excerpt'] ?? $text));
            $review['review_text'] = $text;
            $review['review_text_excerpt'] = $excerpt !== '' ? $excerpt : $text;
            $candidates[] = [
                'review' => $review,
                'position' => $position,
                'verified' => !empty($review['is_verified']),
                'named' => self::hasRealName((string) ($review['reviewer'] ?? '')),
                'key' => mb_strtolower($text, 'UTF-8'),
            ];
        }

        usort($candidates, static function (array $left, array $right): int {
            if ($left['verified'] !== $right['verified']) {
                return $left['verified'] ? -1 : 1;
            }

            if ($left['named'] !== $right['named']) {
                return $left['named'] ? -1 : 1;
            }

            return $left['position'] <=> $right['position'];
        });

        $selected = [];
        $seen = [];
        foreach ($candidates as $candidate) {
            if (isset($seen[$candidate['key']])) {
                continue;
            }

            $seen[$candidate['key']] = true;
            $selected[] = $candidate['review'];
            if (count($selected) === $limit) {
                break;
            }
        }

        return $selected;
    }

    /**
     * ShopVote pseudonyms ("ShopVoter-519741", "Kunde-516380") and anonymized
     * placeholders carry less social proof than a real reviewer name.
     */
    private static function hasRealName(string $reviewer): bool
    {
        $reviewer = trim($reviewer);
        if ($reviewer === '') {
            return false;
        }

        if (preg_match('/^\p{L}+[-_]\d+$/u', $reviewer)) {
            return false;
        }

        return !in_array(mb_strtolower($reviewer, 'UTF-8'), ['anonymous', 'anonym'], true);
    }

    private static function normalizeText(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private static function isMeaningful(string $text): bool
    {
        $marker = mb_strtolower($text, 'UTF-8');
        $marker = (string) preg_replace('/[\p{P}\p{S}\s]+/u', '', $marker);

        return $marker !== '' && !in_array($marker, [
            'ka',
            'na',
            'keineangabe',
            'ohnekommentar',
            'nocomment',
            'nocomments',
        ], true);
    }
}
