<?php
/**
 * ShopVote Reviews - Sync Service
 *
 * Orchestrates the synchronization of data from ShopVote API.
 */

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Service;

use Configuration;
use ShopVote\ShopVoteReviews\Api\ShopVoteApiClient;
use ShopVote\ShopVoteReviews\Api\XmlParser;
use ShopVote\ShopVoteReviews\Api\XmlParseException;
use ShopVote\ShopVoteReviews\Api\ApiResponse;
use ShopVote\ShopVoteReviews\Api\ParsedResponse;
use ShopVote\ShopVoteReviews\Repository\ShopSummaryRepository;
use ShopVote\ShopVoteReviews\Repository\ReviewRepository;
use ShopVote\ShopVoteReviews\Repository\SyncLogRepository;
use ShopVote\ShopVoteReviews\Repository\SyncLockRepository;
use ShopVoteReviews;

class SyncService
{
    /** @var ShopVoteApiClient */
    private $apiClient;

    /** @var XmlParser */
    private $xmlParser;

    /** @var ShopSummaryRepository */
    private $summaryRepository;

    /** @var ReviewRepository */
    private $reviewRepository;

    /** @var SyncLogRepository */
    private $syncLogRepository;

    /** @var SyncLockRepository */
    private $syncLockRepository;

    public function __construct(
        ShopVoteApiClient $apiClient,
        XmlParser $xmlParser,
        ShopSummaryRepository $summaryRepository,
        ReviewRepository $reviewRepository,
        SyncLogRepository $syncLogRepository,
        SyncLockRepository $syncLockRepository
    ) {
        $this->apiClient = $apiClient;
        $this->xmlParser = $xmlParser;
        $this->summaryRepository = $summaryRepository;
        $this->reviewRepository = $reviewRepository;
        $this->syncLogRepository = $syncLogRepository;
        $this->syncLockRepository = $syncLockRepository;
    }

    /**
     * Perform a full sync operation
     *
     * @param bool $force Force sync even if within minimum interval
     *
     * @return SyncResult
     */
    public function sync(bool $force = false): SyncResult
    {
        $result = new SyncResult();

        // Check if module is enabled and configured
        if (!$this->isConfigured()) {
            $result->success = false;
            $result->error = 'Module is not configured. Please set ShopID and API Key.';
            return $result;
        }

        // Check minimum interval
        if (!$force && !$this->canSync()) {
            $result->success = false;
            $result->error = 'Sync skipped: minimum interval not reached.';
            $result->skipped = true;
            return $result;
        }

        // Try to acquire lock
        if (!$this->syncLockRepository->acquireLock()) {
            $result->success = false;
            $result->error = 'Sync already in progress. Please try again later.';
            $result->locked = true;
            return $result;
        }

        try {
            $result = $this->performSync();
        } finally {
            // Always release lock
            $this->syncLockRepository->releaseLock();
        }

        return $result;
    }

    /**
     * Check if the module is configured
     */
    public function isConfigured(): bool
    {
        $shopId = Configuration::get(ShopVoteReviews::CONFIG_KEYS['SHOP_ID']);
        $apiKey = Configuration::get(ShopVoteReviews::CONFIG_KEYS['API_KEY']);

        return !empty($shopId) && !empty($apiKey);
    }

    /**
     * Check if we can sync (based on minimum interval)
     */
    public function canSync(): bool
    {
        $lastFetch = Configuration::get(ShopVoteReviews::CONFIG_KEYS['LAST_FETCH']);
        $minInterval = (int) Configuration::get(ShopVoteReviews::CONFIG_KEYS['MIN_INTERVAL']) ?: 300;

        if (empty($lastFetch)) {
            return true;
        }

        $lastFetchTime = strtotime($lastFetch);
        $nextAllowedTime = $lastFetchTime + $minInterval;

        return time() >= $nextAllowedTime;
    }

    /**
     * Get seconds until next sync is allowed
     */
    public function getSecondsUntilNextSync(): int
    {
        $lastFetch = Configuration::get(ShopVoteReviews::CONFIG_KEYS['LAST_FETCH']);
        $minInterval = (int) Configuration::get(ShopVoteReviews::CONFIG_KEYS['MIN_INTERVAL']) ?: 300;

        if (empty($lastFetch)) {
            return 0;
        }

        $lastFetchTime = strtotime($lastFetch);
        $nextAllowedTime = $lastFetchTime + $minInterval;
        $remaining = $nextAllowedTime - time();

        return max(0, $remaining);
    }

    /**
     * Perform the actual sync operation
     */
    private function performSync(): SyncResult
    {
        $result = new SyncResult();
        $shopId = Configuration::get(ShopVoteReviews::CONFIG_KEYS['SHOP_ID']);
        $apiKey = Configuration::get(ShopVoteReviews::CONFIG_KEYS['API_KEY']);
        $preferredMode = Configuration::get(ShopVoteReviews::CONFIG_KEYS['PREFERRED_MODE']) ?: 'last25ext';

        $parsedResponse = null;
        $actualFunction = $preferredMode;

        // Try preferred mode first
        switch ($preferredMode) {
            case 'last25ext':
                $result = $this->tryLast25Ext($shopId, $apiKey);
                if (!$result->success && $result->shouldFallback) {
                    // Fallback to separate calls
                    $this->syncLogRepository->logWarning(
                        'last25ext',
                        'Falling back to last25 + ratingstars'
                    );
                    $result = $this->trySeparateCalls($shopId, $apiKey);
                    $actualFunction = 'last25+ratingstars';
                }
                break;

            case 'last25_ratingstars':
                $result = $this->trySeparateCalls($shopId, $apiKey);
                $actualFunction = 'last25+ratingstars';
                break;

            case 'ratingstars':
                $result = $this->tryRatingStarsOnly($shopId, $apiKey);
                $actualFunction = 'ratingstars';
                break;

            default:
                $result->success = false;
                $result->error = "Unknown preferred mode: {$preferredMode}";
                return $result;
        }

        // Update last fetch time
        if ($result->success) {
            Configuration::updateValue(ShopVoteReviews::CONFIG_KEYS['LAST_FETCH'], date('Y-m-d H:i:s'));
            Configuration::updateValue(ShopVoteReviews::CONFIG_KEYS['LAST_FETCH_STATUS'], 'success');

            $this->syncLogRepository->logSuccess(
                $actualFunction,
                $result->reviewsUpdated,
                "Synced successfully. Summary: " . ($result->hasSummary ? 'yes' : 'no') .
                ", Reviews: {$result->reviewsUpdated}"
            );

            // Cleanup old data
            $this->cleanup();
        } else {
            Configuration::updateValue(ShopVoteReviews::CONFIG_KEYS['LAST_ERROR'], $result->error);
            Configuration::updateValue(ShopVoteReviews::CONFIG_KEYS['LAST_ERROR_TIME'], date('Y-m-d H:i:s'));

            $this->syncLogRepository->logError(
                $actualFunction,
                $result->httpCode ?? 0,
                $result->error ?? 'Unknown error'
            );
        }

        return $result;
    }

    /**
     * Try to sync using last25ext function
     */
    private function tryLast25Ext(string $shopId, string $apiKey): SyncResult
    {
        $result = new SyncResult();

        $apiResponse = $this->apiClient->fetchLast25Ext($shopId, $apiKey);
        $result->httpCode = $apiResponse->getHttpCode();

        if (!$apiResponse->isSuccess()) {
            $result->success = false;
            $result->error = $apiResponse->getError() ?? "HTTP {$apiResponse->getHttpCode()}";
            $result->shouldFallback = $apiResponse->isPermissionError();
            return $result;
        }

        try {
            $parsedResponse = $this->xmlParser->parse($apiResponse->getBody());
            $this->saveData($parsedResponse, $result);
            $result->success = true;
        } catch (XmlParseException $e) {
            $result->success = false;
            $result->error = 'XML parse error: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Try to sync using separate last25 and ratingstars calls
     */
    private function trySeparateCalls(string $shopId, string $apiKey): SyncResult
    {
        $result = new SyncResult();
        $combinedParsed = new ParsedResponse();

        // First, get rating stars
        $starsResponse = $this->apiClient->fetchRatingStars($shopId, $apiKey);
        if ($starsResponse->isSuccess()) {
            try {
                $starsParsed = $this->xmlParser->parse($starsResponse->getBody());
                // Copy summary data
                $combinedParsed->shopId = $starsParsed->shopId;
                $combinedParsed->shopName = $starsParsed->shopName;
                $combinedParsed->profileUrl = $starsParsed->profileUrl;
                $combinedParsed->shopUrl = $starsParsed->shopUrl;
                $combinedParsed->lastVote = $starsParsed->lastVote;
                $combinedParsed->hasSummary = $starsParsed->hasSummary;
                $combinedParsed->ratingValueStars = $starsParsed->ratingValueStars;
                $combinedParsed->ratingValueScore = $starsParsed->ratingValueScore;
                $combinedParsed->ratingWord = $starsParsed->ratingWord;
                $combinedParsed->ratingsCount = $starsParsed->ratingsCount;
                $combinedParsed->ratingsPositive = $starsParsed->ratingsPositive;
                $combinedParsed->ratingsNeutral = $starsParsed->ratingsNeutral;
                $combinedParsed->ratingsNegative = $starsParsed->ratingsNegative;
                $combinedParsed->commentsCount = $starsParsed->commentsCount;
            } catch (XmlParseException $e) {
                // Continue without summary
            }
        }

        // Then, get reviews
        $reviewsResponse = $this->apiClient->fetchLast25($shopId, $apiKey);
        $result->httpCode = $reviewsResponse->getHttpCode();

        if ($reviewsResponse->isSuccess()) {
            try {
                $reviewsParsed = $this->xmlParser->parse($reviewsResponse->getBody());
                $combinedParsed->hasReviews = $reviewsParsed->hasReviews;
                $combinedParsed->reviews = $reviewsParsed->reviews;

                // Fill in shop info if not from stars
                if ($combinedParsed->shopId === null) {
                    $combinedParsed->shopId = $reviewsParsed->shopId;
                    $combinedParsed->shopName = $reviewsParsed->shopName;
                    $combinedParsed->profileUrl = $reviewsParsed->profileUrl;
                    $combinedParsed->shopUrl = $reviewsParsed->shopUrl;
                }
            } catch (XmlParseException $e) {
                $result->success = false;
                $result->error = 'XML parse error (reviews): ' . $e->getMessage();
                return $result;
            }
        } else {
            $result->success = false;
            $result->error = $reviewsResponse->getError() ?? "HTTP {$reviewsResponse->getHttpCode()}";
            return $result;
        }

        // Save combined data
        $this->saveData($combinedParsed, $result);
        $result->success = true;

        return $result;
    }

    /**
     * Try to sync using only ratingstars function
     */
    private function tryRatingStarsOnly(string $shopId, string $apiKey): SyncResult
    {
        $result = new SyncResult();

        $apiResponse = $this->apiClient->fetchRatingStars($shopId, $apiKey);
        $result->httpCode = $apiResponse->getHttpCode();

        if (!$apiResponse->isSuccess()) {
            $result->success = false;
            $result->error = $apiResponse->getError() ?? "HTTP {$apiResponse->getHttpCode()}";
            return $result;
        }

        try {
            $parsedResponse = $this->xmlParser->parse($apiResponse->getBody());
            $this->saveData($parsedResponse, $result);
            $result->success = true;
        } catch (XmlParseException $e) {
            $result->success = false;
            $result->error = 'XML parse error: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Save parsed data to database
     */
    private function saveData(ParsedResponse $parsed, SyncResult $result): void
    {
        // Save summary
        if ($parsed->hasSummary || $parsed->shopId !== null) {
            $this->summaryRepository->saveSummary($parsed);
            $result->hasSummary = true;
        }

        // Save reviews
        if ($parsed->hasReviews) {
            foreach ($parsed->reviews as $review) {
                $this->reviewRepository->saveReview($review);
                $result->reviewsUpdated++;
            }
        }
    }

    /**
     * Cleanup old data based on retention settings
     */
    private function cleanup(): void
    {
        $logRetention = (int) Configuration::get(ShopVoteReviews::CONFIG_KEYS['LOG_RETENTION_COUNT']) ?: 10;
        $dataRetention = (int) Configuration::get(ShopVoteReviews::CONFIG_KEYS['DATA_RETENTION_DAYS']) ?: 365;

        $this->summaryRepository->cleanupOldSummaries($logRetention);
        $this->syncLogRepository->cleanupOldLogs($logRetention);

        if ($dataRetention > 0) {
            $this->reviewRepository->cleanupOldReviews($dataRetention);
        }
    }

    /**
     * Purge all data (admin action)
     */
    public function purgeAllData(): bool
    {
        $this->summaryRepository->purgeAll();
        $this->reviewRepository->purgeAll();
        $this->syncLogRepository->purgeAll();
        $this->syncLockRepository->forceReleaseAllLocks();

        // Reset last fetch
        Configuration::updateValue(ShopVoteReviews::CONFIG_KEYS['LAST_FETCH'], '');
        Configuration::updateValue(ShopVoteReviews::CONFIG_KEYS['LAST_FETCH_STATUS'], '');
        Configuration::updateValue(ShopVoteReviews::CONFIG_KEYS['LAST_ERROR'], '');
        Configuration::updateValue(ShopVoteReviews::CONFIG_KEYS['LAST_ERROR_TIME'], '');

        return true;
    }

    /**
     * Get sync status for admin display
     */
    public function getSyncStatus(): array
    {
        $lastFetch = Configuration::get(ShopVoteReviews::CONFIG_KEYS['LAST_FETCH']);
        $lastError = Configuration::get(ShopVoteReviews::CONFIG_KEYS['LAST_ERROR']);
        $lastErrorTime = Configuration::get(ShopVoteReviews::CONFIG_KEYS['LAST_ERROR_TIME']);

        return [
            'is_configured' => $this->isConfigured(),
            'can_sync' => $this->canSync(),
            'seconds_until_next_sync' => $this->getSecondsUntilNextSync(),
            'last_fetch' => $lastFetch ?: null,
            'last_error' => $lastError ?: null,
            'last_error_time' => $lastErrorTime ?: null,
            'is_locked' => $this->syncLockRepository->isLocked(),
            'summary_count' => $this->summaryRepository->getSummaryCount(),
            'review_count' => $this->reviewRepository->getReviewCount(),
            'recent_logs' => $this->syncLogRepository->getRecentLogs(5),
        ];
    }
}
