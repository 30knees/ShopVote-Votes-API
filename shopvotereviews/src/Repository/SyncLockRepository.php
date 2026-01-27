<?php
/**
 * ShopVote Reviews - Sync Lock Repository
 *
 * Database operations for managing sync locks to prevent concurrent execution.
 */

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Repository;

use Db;
use Context;

class SyncLockRepository
{
    /** @var string Lock key for sync operations */
    private const LOCK_KEY = 'shopvote_sync';

    /** @var int Lock timeout in seconds */
    private const LOCK_TIMEOUT = 300; // 5 minutes

    /**
     * Acquire a sync lock
     *
     * @return bool True if lock was acquired, false if already locked
     */
    public function acquireLock(?int $shopId = null): bool
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;

        // First, clean up expired locks
        $this->cleanupExpiredLocks($shopId);

        // Check if lock exists
        if ($this->isLocked($shopId)) {
            return false;
        }

        // Try to insert lock
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + self::LOCK_TIMEOUT);

        $data = [
            'lock_key' => pSQL(self::LOCK_KEY),
            'locked_at' => $now,
            'expires_at' => $expiresAt,
            'id_shop' => (int) $shopId,
        ];

        try {
            return Db::getInstance()->insert('shopvote_sync_lock', $data);
        } catch (\Exception $e) {
            // Duplicate key - lock already exists
            return false;
        }
    }

    /**
     * Release the sync lock
     */
    public function releaseLock(?int $shopId = null): bool
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;

        return Db::getInstance()->delete(
            'shopvote_sync_lock',
            'lock_key = \'' . pSQL(self::LOCK_KEY) . '\' AND id_shop = ' . (int) $shopId
        );
    }

    /**
     * Check if sync is currently locked
     */
    public function isLocked(?int $shopId = null): bool
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;

        $sql = new \DbQuery();
        $sql->select('COUNT(*) as cnt');
        $sql->from('shopvote_sync_lock');
        $sql->where('lock_key = \'' . pSQL(self::LOCK_KEY) . '\'');
        $sql->where('id_shop = ' . (int) $shopId);
        $sql->where('expires_at > \'' . date('Y-m-d H:i:s') . '\'');

        $result = Db::getInstance()->getRow($sql);

        return (int) ($result['cnt'] ?? 0) > 0;
    }

    /**
     * Get lock info
     */
    public function getLockInfo(?int $shopId = null): ?array
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;

        $sql = new \DbQuery();
        $sql->select('*');
        $sql->from('shopvote_sync_lock');
        $sql->where('lock_key = \'' . pSQL(self::LOCK_KEY) . '\'');
        $sql->where('id_shop = ' . (int) $shopId);

        $result = Db::getInstance()->getRow($sql);

        return $result ?: null;
    }

    /**
     * Cleanup expired locks
     */
    public function cleanupExpiredLocks(?int $shopId = null): int
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;

        Db::getInstance()->delete(
            'shopvote_sync_lock',
            'id_shop = ' . (int) $shopId . ' AND expires_at <= \'' . date('Y-m-d H:i:s') . '\''
        );

        return Db::getInstance()->Affected_Rows();
    }

    /**
     * Force release all locks (admin action)
     */
    public function forceReleaseAllLocks(?int $shopId = null): bool
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;

        return Db::getInstance()->delete('shopvote_sync_lock', 'id_shop = ' . (int) $shopId);
    }
}
