<?php
/**
 * ShopVote Reviews - Review Repository
 *
 * Database operations for reviews and answers.
 */

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Repository;

use Db;
use Context;
use ShopVote\ShopVoteReviews\Api\ParsedReview;
use ShopVote\ShopVoteReviews\Security\ShopVoteUrlValidator;

class ReviewRepository
{
    /**
     * Get latest reviews
     */
    public function getLatestReviews(int $limit = 5, ?int $shopId = null): array
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;

        $sql = new \DbQuery();
        $sql->select('*');
        $sql->from('shopvote_review');
        $sql->where('id_shop = ' . (int) $shopId);
        $sql->orderBy('review_date DESC');
        $sql->limit($limit);

        $results = Db::getInstance()->executeS($sql);

        return $results ?: [];
    }

    /**
     * Get all reviews (for admin display)
     */
    public function getAllReviews(int $page = 1, int $perPage = 25, ?int $shopId = null): array
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;
        $offset = ($page - 1) * $perPage;

        $sql = new \DbQuery();
        $sql->select('*');
        $sql->from('shopvote_review');
        $sql->where('id_shop = ' . (int) $shopId);
        $sql->orderBy('review_date DESC');
        $sql->limit($perPage, $offset);

        $results = Db::getInstance()->executeS($sql);

        return $results ?: [];
    }

    /**
     * Get review by ShopVote review ID
     */
    public function getReviewByReviewId(string $reviewId, ?int $shopId = null): ?array
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;

        $sql = new \DbQuery();
        $sql->select('*');
        $sql->from('shopvote_review');
        $sql->where('review_id = \'' . pSQL($reviewId) . '\'');
        $sql->where('id_shop = ' . (int) $shopId);

        $result = Db::getInstance()->getRow($sql);

        return $result ?: null;
    }

    /**
     * Get answers for a review
     */
    public function getReviewAnswers(int $reviewId, ?int $shopId = null): array
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;

        // First get the ShopVote review_id from our internal id
        $review = $this->getReviewById($reviewId, $shopId);
        if (!$review) {
            return [];
        }

        return $this->getAnswersByReviewId($review['review_id'], $shopId);
    }

    /**
     * Get review by internal ID
     */
    public function getReviewById(int $id, ?int $shopId = null): ?array
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;

        $sql = new \DbQuery();
        $sql->select('*');
        $sql->from('shopvote_review');
        $sql->where('id_review = ' . (int) $id);
        $sql->where('id_shop = ' . (int) $shopId);

        $result = Db::getInstance()->getRow($sql);

        return $result ?: null;
    }

    /**
     * Get answers by ShopVote review ID
     */
    public function getAnswersByReviewId(string $reviewId, ?int $shopId = null): array
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;

        $sql = new \DbQuery();
        $sql->select('*');
        $sql->from('shopvote_review_answer');
        $sql->where('review_id = \'' . pSQL($reviewId) . '\'');
        $sql->where('id_shop = ' . (int) $shopId);
        $sql->orderBy('answer_date ASC');

        $results = Db::getInstance()->executeS($sql);

        return $results ?: [];
    }

    /**
     * Batch-load answers keyed by ShopVote review ID.
     *
     * @param string[] $reviewIds
     */
    public function getAnswersByReviewIds(array $reviewIds, ?int $shopId = null): array
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;
        $reviewIds = array_values(array_unique(array_filter($reviewIds, static fn ($id): bool => is_string($id) && $id !== '')));

        if ($reviewIds === []) {
            return [];
        }

        $quotedIds = array_map(static fn (string $id): string => '\'' . pSQL($id) . '\'', $reviewIds);
        $sql = new \DbQuery();
        $sql->select('*');
        $sql->from('shopvote_review_answer');
        $sql->where('id_shop = ' . (int) $shopId);
        $sql->where('review_id IN (' . implode(',', $quotedIds) . ')');
        $sql->orderBy('review_id ASC, answer_date ASC');

        $answersByReview = [];
        foreach (Db::getInstance()->executeS($sql) ?: [] as $answer) {
            $answersByReview[$answer['review_id']][] = $answer;
        }

        return $answersByReview;
    }

    /**
     * Save or update a review (upsert)
     *
     * @return int Database affected-row count, or -1 on failure
     */
    public function saveReview(ParsedReview $review, ?int $shopId = null): int
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;

        if ($review->reviewId === null
            || $review->reviewId === ''
            || strlen($review->reviewId) > 64
            || preg_match('/[\x00-\x1F\x7F]/', $review->reviewId)) {
            return -1;
        }

        $data = [
            'review_id' => pSQL($review->reviewId ?? ''),
            'review_url' => ($reviewUrl = ShopVoteUrlValidator::normalize($review->reviewUrl)) !== null ? pSQL($reviewUrl) : null,
            'review_date' => $review->reviewDate !== null ? $review->reviewDate->format('Y-m-d H:i:s') : null,
            'reviewer' => pSQL($review->reviewer ?? ''),
            'review_rating_stars' => $review->reviewRatingStars !== null ? (float) $review->reviewRatingStars : null,
            'review_text' => pSQL($review->reviewText ?? '', true),
            'is_verified' => $review->isVerified ? 1 : 0,
            'fetched_at' => date('Y-m-d H:i:s'),
            'last_seen_at' => date('Y-m-d H:i:s'),
            'id_shop' => (int) $shopId,
        ];

        if (!Db::getInstance()->insert('shopvote_review', $data, true, true, Db::ON_DUPLICATE_KEY)) {
            return -1;
        }
        $affectedReviews = Db::getInstance()->Affected_Rows();

        // Save answers (replace all for this review)
        if (!$this->saveAnswers($review, $shopId)) {
            return -1;
        }

        return $affectedReviews;
    }

    /**
     * Save review answers (replaces all existing answers)
     */
    private function saveAnswers(ParsedReview $review, int $shopId): bool
    {
        if (empty($review->reviewId)) {
            return false;
        }

        // Delete existing answers for this review
        if (!Db::getInstance()->delete(
            'shopvote_review_answer',
            'review_id = \'' . pSQL($review->reviewId) . '\' AND id_shop = ' . (int) $shopId
        )) {
            return false;
        }

        // Insert new answers
        foreach ($review->answers as $answer) {
            $data = [
                'review_id' => pSQL($review->reviewId),
                'answer_type' => pSQL($answer->type ?? 'Unknown'),
                'answer_date' => $answer->date !== null ? $answer->date->format('Y-m-d H:i:s') : null,
                'answer_text' => pSQL($answer->text ?? '', true),
                'id_shop' => (int) $shopId,
            ];

            if (!Db::getInstance()->insert('shopvote_review_answer', $data, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get review count
     */
    public function getReviewCount(?int $shopId = null): int
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;

        $sql = new \DbQuery();
        $sql->select('COUNT(*) as cnt');
        $sql->from('shopvote_review');
        $sql->where('id_shop = ' . (int) $shopId);

        $result = Db::getInstance()->getRow($sql);

        return (int) ($result['cnt'] ?? 0);
    }

    /**
     * Merchant-facing review health summary. Counts only, with no customer data.
     */
    public function getReviewHealth(int $days = 30, ?int $shopId = null): array
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;
        $days = max(1, min(365, $days));
        $table = '`' . _DB_PREFIX_ . 'shopvote_review`';
        $answerTable = '`' . _DB_PREFIX_ . 'shopvote_review_answer`';
        $sql = 'SELECT
                    COUNT(*) AS total,
                    SUM(r.first_seen_at >= DATE_SUB(NOW(), INTERVAL ' . (int) $days . ' DAY)) AS new_reviews,
                    SUM(r.is_verified = 1 AND r.first_seen_at >= DATE_SUB(NOW(), INTERVAL ' . (int) $days . ' DAY)) AS new_verified,
                    SUM(r.review_rating_stars >= 4 AND r.review_date >= DATE_SUB(NOW(), INTERVAL ' . (int) $days . ' DAY)) AS positive_current,
                    SUM(r.review_rating_stars >= 3 AND r.review_rating_stars < 4 AND r.review_date >= DATE_SUB(NOW(), INTERVAL ' . (int) $days . ' DAY)) AS neutral_current,
                    SUM(r.review_rating_stars < 3 AND r.review_date >= DATE_SUB(NOW(), INTERVAL ' . (int) $days . ' DAY)) AS negative_current,
                    SUM(r.review_rating_stars >= 4 AND r.review_date >= DATE_SUB(NOW(), INTERVAL ' . (int) ($days * 2) . ' DAY) AND r.review_date < DATE_SUB(NOW(), INTERVAL ' . (int) $days . ' DAY)) AS positive_previous,
                    SUM(r.review_rating_stars >= 3 AND r.review_rating_stars < 4 AND r.review_date >= DATE_SUB(NOW(), INTERVAL ' . (int) ($days * 2) . ' DAY) AND r.review_date < DATE_SUB(NOW(), INTERVAL ' . (int) $days . ' DAY)) AS neutral_previous,
                    SUM(r.review_rating_stars < 3 AND r.review_date >= DATE_SUB(NOW(), INTERVAL ' . (int) ($days * 2) . ' DAY) AND r.review_date < DATE_SUB(NOW(), INTERVAL ' . (int) $days . ' DAY)) AS negative_previous,
                    SUM((r.review_rating_stars < 4) AND NOT EXISTS (
                        SELECT 1 FROM ' . $answerTable . ' a
                        WHERE a.review_id = r.review_id AND a.id_shop = r.id_shop AND a.answer_type = \'Shop\'
                    )) AS unanswered_attention,
                    SUM(EXISTS (
                        SELECT 1 FROM ' . $answerTable . ' a
                        WHERE a.review_id = r.review_id AND a.id_shop = r.id_shop AND a.answer_type = \'Shop\'
                    )) AS answered
                FROM ' . $table . ' r
                WHERE r.id_shop = ' . (int) $shopId;

        $row = Db::getInstance()->getRow($sql) ?: [];
        $total = (int) ($row['total'] ?? 0);

        return [
            'new_reviews' => (int) ($row['new_reviews'] ?? 0),
            'new_verified' => (int) ($row['new_verified'] ?? 0),
            'positive_current' => (int) ($row['positive_current'] ?? 0),
            'neutral_current' => (int) ($row['neutral_current'] ?? 0),
            'negative_current' => (int) ($row['negative_current'] ?? 0),
            'positive_previous' => (int) ($row['positive_previous'] ?? 0),
            'neutral_previous' => (int) ($row['neutral_previous'] ?? 0),
            'negative_previous' => (int) ($row['negative_previous'] ?? 0),
            'unanswered_attention' => (int) ($row['unanswered_attention'] ?? 0),
            'response_coverage' => $total > 0 ? round(((int) ($row['answered'] ?? 0) / $total) * 100, 1) : 0.0,
        ];
    }

    /**
     * Delete old reviews based on retention days
     */
    public function cleanupOldReviews(int $retentionDays = 365, ?int $shopId = null): int
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;

        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

        // Get review IDs to delete
        $sql = new \DbQuery();
        $sql->select('review_id');
        $sql->from('shopvote_review');
        $sql->where('id_shop = ' . (int) $shopId);
        $sql->where('last_seen_at < \'' . pSQL($cutoffDate) . '\'');

        $results = Db::getInstance()->executeS($sql);
        $reviewIds = array_column($results ?: [], 'review_id');

        if (empty($reviewIds)) {
            return 0;
        }

        // Delete answers for these reviews
        $reviewIdsQuoted = array_map(fn($id) => '\'' . pSQL($id) . '\'', $reviewIds);
        if (!Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'shopvote_review_answer`
             WHERE id_shop = ' . (int) $shopId . '
             AND review_id IN (' . implode(',', $reviewIdsQuoted) . ')'
        )) {
            return 0;
        }

        // Delete reviews
        if (!Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'shopvote_review`
             WHERE id_shop = ' . (int) $shopId . '
             AND last_seen_at < \'' . pSQL($cutoffDate) . '\''
        )) {
            return 0;
        }

        return Db::getInstance()->Affected_Rows();
    }

    /**
     * Purge all reviews and answers
     */
    public function purgeAll(?int $shopId = null): bool
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;

        return Db::getInstance()->delete('shopvote_review_answer', 'id_shop = ' . (int) $shopId)
            && Db::getInstance()->delete('shopvote_review', 'id_shop = ' . (int) $shopId);
    }
}
