<?php
/**
 * ShopVote Reviews - Shop Summary Repository
 *
 * Database operations for shop summary data.
 */

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Repository;

use Db;
use Context;
use ShopVote\ShopVoteReviews\Api\ParsedResponse;

class ShopSummaryRepository
{
    /**
     * Get the latest shop summary
     */
    public function getLatestSummary(?int $shopId = null): ?array
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;

        $sql = new \DbQuery();
        $sql->select('*');
        $sql->from('shopvote_shop_summary');
        $sql->where('id_shop = ' . (int) $shopId);
        $sql->orderBy('fetched_at DESC');
        $sql->limit(1);

        $result = Db::getInstance()->getRow($sql);

        return $result ?: null;
    }

    /**
     * Save or update shop summary from parsed response
     */
    public function saveSummary(ParsedResponse $response, ?int $shopId = null): bool
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;

        $data = [
            'shop_id' => pSQL($response->shopId ?? ''),
            'shop_name' => pSQL($response->shopName ?? ''),
            'rating_value_stars' => $response->ratingValueStars !== null ? (float) $response->ratingValueStars : null,
            'rating_value_score' => $response->ratingValueScore !== null ? (float) $response->ratingValueScore : null,
            'rating_word' => pSQL($response->ratingWord ?? ''),
            'ratings_count' => (int) ($response->ratingsCount ?? 0),
            'ratings_positive' => (int) ($response->ratingsPositive ?? 0),
            'ratings_neutral' => (int) ($response->ratingsNeutral ?? 0),
            'ratings_negative' => (int) ($response->ratingsNegative ?? 0),
            'comments_count' => (int) ($response->commentsCount ?? 0),
            'profile_url' => pSQL($response->profileUrl ?? ''),
            'shop_url' => pSQL($response->shopUrl ?? ''),
            'last_vote' => $response->lastVote !== null ? $response->lastVote->format('Y-m-d H:i:s') : null,
            'fetched_at' => date('Y-m-d H:i:s'),
            'id_shop' => (int) $shopId,
        ];

        return Db::getInstance()->insert('shopvote_shop_summary', $data);
    }

    /**
     * Delete old summaries, keeping only the most recent N records
     */
    public function cleanupOldSummaries(int $keepCount = 10, ?int $shopId = null): int
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;

        // Get IDs to keep
        $sql = new \DbQuery();
        $sql->select('id_summary');
        $sql->from('shopvote_shop_summary');
        $sql->where('id_shop = ' . (int) $shopId);
        $sql->orderBy('fetched_at DESC');
        $sql->limit($keepCount);

        $results = Db::getInstance()->executeS($sql);
        $keepIds = array_column($results ?: [], 'id_summary');

        if (empty($keepIds)) {
            return 0;
        }

        // Delete older records
        $deleteSql = 'DELETE FROM `' . _DB_PREFIX_ . 'shopvote_shop_summary`
                      WHERE id_shop = ' . (int) $shopId . '
                      AND id_summary NOT IN (' . implode(',', array_map('intval', $keepIds)) . ')';

        Db::getInstance()->execute($deleteSql);

        return Db::getInstance()->Affected_Rows();
    }

    /**
     * Get summary count
     */
    public function getSummaryCount(?int $shopId = null): int
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;

        $sql = new \DbQuery();
        $sql->select('COUNT(*) as cnt');
        $sql->from('shopvote_shop_summary');
        $sql->where('id_shop = ' . (int) $shopId);

        $result = Db::getInstance()->getRow($sql);

        return (int) ($result['cnt'] ?? 0);
    }

    /**
     * Purge all summaries
     */
    public function purgeAll(?int $shopId = null): bool
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;

        return Db::getInstance()->delete('shopvote_shop_summary', 'id_shop = ' . (int) $shopId);
    }
}
