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
     * Save or update a review (upsert)
     *
     * @return int Number of affected reviews (1 if inserted/updated, 0 otherwise)
     */
    public function saveReview(ParsedReview $review, ?int $shopId = null): int
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;

        $data = [
            'review_id' => pSQL($review->reviewId ?? ''),
            'review_url' => pSQL($review->reviewUrl ?? ''),
            'review_date' => $review->reviewDate !== null ? $review->reviewDate->format('Y-m-d H:i:s') : null,
            'reviewer' => pSQL($review->reviewer ?? ''),
            'review_rating_stars' => $review->reviewRatingStars !== null ? (float) $review->reviewRatingStars : null,
            'review_text' => pSQL($review->reviewText ?? '', true),
            'is_verified' => $review->isVerified ? 1 : 0,
            'fetched_at' => date('Y-m-d H:i:s'),
            'id_shop' => (int) $shopId,
        ];

        // Check if review exists
        $existingReview = $this->getReviewByReviewId($review->reviewId, $shopId);

        if ($existingReview) {
            // Update existing review
            $where = 'review_id = \'' . pSQL($review->reviewId) . '\' AND id_shop = ' . (int) $shopId;
            Db::getInstance()->update('shopvote_review', $data, $where);
        } else {
            // Insert new review
            Db::getInstance()->insert('shopvote_review', $data);
        }

        // Save answers (replace all for this review)
        $this->saveAnswers($review, $shopId);

        return 1;
    }

    /**
     * Save review answers (replaces all existing answers)
     */
    private function saveAnswers(ParsedReview $review, int $shopId): void
    {
        if (empty($review->reviewId)) {
            return;
        }

        // Delete existing answers for this review
        Db::getInstance()->delete(
            'shopvote_review_answer',
            'review_id = \'' . pSQL($review->reviewId) . '\' AND id_shop = ' . (int) $shopId
        );

        // Insert new answers
        foreach ($review->answers as $answer) {
            $data = [
                'review_id' => pSQL($review->reviewId),
                'answer_type' => pSQL($answer->type ?? 'Unknown'),
                'answer_date' => $answer->date !== null ? $answer->date->format('Y-m-d H:i:s') : null,
                'answer_text' => pSQL($answer->text ?? '', true),
                'id_shop' => (int) $shopId,
            ];

            Db::getInstance()->insert('shopvote_review_answer', $data);
        }
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
        $sql->where('fetched_at < \'' . pSQL($cutoffDate) . '\'');

        $results = Db::getInstance()->executeS($sql);
        $reviewIds = array_column($results ?: [], 'review_id');

        if (empty($reviewIds)) {
            return 0;
        }

        // Delete answers for these reviews
        $reviewIdsQuoted = array_map(fn($id) => '\'' . pSQL($id) . '\'', $reviewIds);
        Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'shopvote_review_answer`
             WHERE id_shop = ' . (int) $shopId . '
             AND review_id IN (' . implode(',', $reviewIdsQuoted) . ')'
        );

        // Delete reviews
        Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'shopvote_review`
             WHERE id_shop = ' . (int) $shopId . '
             AND fetched_at < \'' . pSQL($cutoffDate) . '\''
        );

        return Db::getInstance()->Affected_Rows();
    }

    /**
     * Purge all reviews and answers
     */
    public function purgeAll(?int $shopId = null): bool
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;

        Db::getInstance()->delete('shopvote_review_answer', 'id_shop = ' . (int) $shopId);
        Db::getInstance()->delete('shopvote_review', 'id_shop = ' . (int) $shopId);

        return true;
    }
}
