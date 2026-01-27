<?php
/**
 * ShopVote Reviews - Sync Log Repository
 *
 * Database operations for sync logs.
 */

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Repository;

use Db;
use Context;

class SyncLogRepository
{
    /**
     * Log a sync operation
     */
    public function log(
        string $function,
        string $status,
        ?int $httpCode = null,
        int $reviewsUpdated = 0,
        ?string $message = null,
        ?int $shopId = null
    ): bool {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;

        $data = [
            'sync_time' => date('Y-m-d H:i:s'),
            'sync_function' => pSQL($function),
            'status' => pSQL($status),
            'http_code' => $httpCode,
            'reviews_updated' => (int) $reviewsUpdated,
            'message' => pSQL($message ?? '', true),
            'id_shop' => (int) $shopId,
        ];

        return Db::getInstance()->insert('shopvote_sync_log', $data);
    }

    /**
     * Log a successful sync
     */
    public function logSuccess(string $function, int $reviewsUpdated = 0, ?string $message = null, ?int $shopId = null): bool
    {
        return $this->log($function, 'success', 200, $reviewsUpdated, $message, $shopId);
    }

    /**
     * Log a failed sync
     */
    public function logError(string $function, int $httpCode, string $message, ?int $shopId = null): bool
    {
        return $this->log($function, 'error', $httpCode, 0, $message, $shopId);
    }

    /**
     * Log a warning (e.g., fallback to different function)
     */
    public function logWarning(string $function, string $message, ?int $shopId = null): bool
    {
        return $this->log($function, 'warning', null, 0, $message, $shopId);
    }

    /**
     * Get recent sync logs
     */
    public function getRecentLogs(int $limit = 10, ?int $shopId = null): array
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;

        $sql = new \DbQuery();
        $sql->select('*');
        $sql->from('shopvote_sync_log');
        $sql->where('id_shop = ' . (int) $shopId);
        $sql->orderBy('sync_time DESC');
        $sql->limit($limit);

        $results = Db::getInstance()->executeS($sql);

        return $results ?: [];
    }

    /**
     * Get last successful sync time
     */
    public function getLastSuccessfulSyncTime(?int $shopId = null): ?string
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;

        $sql = new \DbQuery();
        $sql->select('sync_time');
        $sql->from('shopvote_sync_log');
        $sql->where('id_shop = ' . (int) $shopId);
        $sql->where('status = \'success\'');
        $sql->orderBy('sync_time DESC');
        $sql->limit(1);

        $result = Db::getInstance()->getRow($sql);

        return $result ? $result['sync_time'] : null;
    }

    /**
     * Get last error
     */
    public function getLastError(?int $shopId = null): ?array
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;

        $sql = new \DbQuery();
        $sql->select('*');
        $sql->from('shopvote_sync_log');
        $sql->where('id_shop = ' . (int) $shopId);
        $sql->where('status = \'error\'');
        $sql->orderBy('sync_time DESC');
        $sql->limit(1);

        $result = Db::getInstance()->getRow($sql);

        return $result ?: null;
    }

    /**
     * Cleanup old logs, keeping only the most recent N records
     */
    public function cleanupOldLogs(int $keepCount = 10, ?int $shopId = null): int
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;

        // Get IDs to keep
        $sql = new \DbQuery();
        $sql->select('id_log');
        $sql->from('shopvote_sync_log');
        $sql->where('id_shop = ' . (int) $shopId);
        $sql->orderBy('sync_time DESC');
        $sql->limit($keepCount);

        $results = Db::getInstance()->executeS($sql);
        $keepIds = array_column($results ?: [], 'id_log');

        if (empty($keepIds)) {
            return 0;
        }

        // Delete older logs
        $deleteSql = 'DELETE FROM `' . _DB_PREFIX_ . 'shopvote_sync_log`
                      WHERE id_shop = ' . (int) $shopId . '
                      AND id_log NOT IN (' . implode(',', array_map('intval', $keepIds)) . ')';

        Db::getInstance()->execute($deleteSql);

        return Db::getInstance()->Affected_Rows();
    }

    /**
     * Purge all logs
     */
    public function purgeAll(?int $shopId = null): bool
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;

        return Db::getInstance()->delete('shopvote_sync_log', 'id_shop = ' . (int) $shopId);
    }
}
