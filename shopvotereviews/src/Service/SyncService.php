<?php
/**
 * ShopVote Reviews - Sync Service
 *
 * Orchestrates the synchronization of data from ShopVote API.
 */

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Service;

use Configuration;
use Db;
use ShopVote\ShopVoteReviews\Api\ShopVoteApiClient;
use ShopVote\ShopVoteReviews\Api\XmlParser;
use ShopVote\ShopVoteReviews\Api\XmlParseException;
use ShopVote\ShopVoteReviews\Api\ApiResponse;
use ShopVote\ShopVoteReviews\Api\ParsedResponse;
use ShopVote\ShopVoteReviews\Repository\ShopSummaryRepository;
use ShopVote\ShopVoteReviews\Repository\ReviewRepository;
use ShopVote\ShopVoteReviews\Repository\SyncLogRepository;
use ShopVote\ShopVoteReviews\Repository\SyncLockRepository;
use ShopVote\ShopVoteReviews\Repository\MetricsRepository;
use ShopVoteReviews;
use ShopVote\ShopVoteReviews\Support\ConfigurationValue;

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

    /** @var MetricsRepository */
    private $metricsRepository;

    public function __construct(
        ShopVoteApiClient $apiClient,
        XmlParser $xmlParser,
        ShopSummaryRepository $summaryRepository,
        ReviewRepository $reviewRepository,
        SyncLogRepository $syncLogRepository,
        SyncLockRepository $syncLockRepository,
        MetricsRepository $metricsRepository
    ) {
        $this->apiClient = $apiClient;
        $this->xmlParser = $xmlParser;
        $this->summaryRepository = $summaryRepository;
        $this->reviewRepository = $reviewRepository;
        $this->syncLogRepository = $syncLogRepository;
        $this->syncLockRepository = $syncLockRepository;
        $this->metricsRepository = $metricsRepository;
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
        } catch (\Throwable $e) {
            $result->error = 'The synchronized data could not be persisted.';
            Configuration::updateValue(ShopVoteReviews::CONFIG_KEYS['LAST_ERROR'], $result->error);
            Configuration::updateValue(ShopVoteReviews::CONFIG_KEYS['LAST_ERROR_TIME'], date('Y-m-d H:i:s'));
            $this->syncLogRepository->logError('persistence', 0, $e->getMessage());
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
        $minInterval = $this->getIntegerConfiguration('MIN_INTERVAL', 300);

        if (empty($lastFetch)) {
            return true;
        }

        $lastFetchTime = strtotime($lastFetch);
        if ($lastFetchTime === false) {
            return true;
        }

        $nextAllowedTime = $lastFetchTime + $minInterval;

        return time() >= $nextAllowedTime;
    }

    /**
     * Get seconds until next sync is allowed
     */
    public function getSecondsUntilNextSync(): int
    {
        $lastFetch = Configuration::get(ShopVoteReviews::CONFIG_KEYS['LAST_FETCH']);
        $minInterval = $this->getIntegerConfiguration('MIN_INTERVAL', 300);

        if (empty($lastFetch)) {
            return 0;
        }

        $lastFetchTime = strtotime($lastFetch);
        if ($lastFetchTime === false) {
            return 0;
        }

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
            Configuration::updateValue(ShopVoteReviews::CONFIG_KEYS['LAST_FETCH_STATUS'], $result->partial ? 'partial' : 'success');
            Configuration::updateValue(ShopVoteReviews::CONFIG_KEYS['LAST_ERROR'], '');
            Configuration::updateValue(ShopVoteReviews::CONFIG_KEYS['LAST_ERROR_TIME'], '');

            $message = ($result->partial ? 'Partially synced. ' : 'Synced successfully. ') .
                'Summary: ' . ($result->hasSummary ? 'yes' : 'no') .
                ", Reviews: {$result->reviewsUpdated}" .
                ($result->warnings ? ', Warnings: ' . implode(' ', $result->warnings) : '');

            if ($result->partial) {
                $this->syncLogRepository->log(
                    $actualFunction,
                    'warning',
                    $result->httpCode,
                    $result->reviewsUpdated,
                    $message
                );
            } else {
                $this->syncLogRepository->logSuccess($actualFunction, $result->reviewsUpdated, $message);
            }

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
            $result->components['summary'] = $parsedResponse->hasSummary ? 'success' : 'unavailable';
            $result->components['reviews'] = $parsedResponse->hasReviews ? 'success' : 'unavailable';
            $result->partial = !$parsedResponse->hasSummary || !$parsedResponse->hasReviews;
            if (!$parsedResponse->hasSummary) {
                $result->warnings[] = 'The combined response contained no summary; the previous summary was preserved.';
            }
            if (!$parsedResponse->hasReviews) {
                $result->warnings[] = 'The combined response contained no reviews.';
            }
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
                $result->components['summary'] = $starsParsed->hasSummary ? 'success' : 'unavailable';
                if (!$starsParsed->hasSummary) {
                    $result->warnings[] = 'The rating summary response contained no summary data; the previous summary was preserved.';
                }
            } catch (XmlParseException $e) {
                $result->components['summary'] = 'failed';
                $result->warnings[] = 'Rating summary XML was invalid; the previous summary was preserved.';
            }
        } else {
            $result->components['summary'] = 'failed';
            $result->warnings[] = 'Rating summary request failed; the previous summary was preserved.';
        }

        // Then, get reviews
        $reviewsResponse = $this->apiClient->fetchLast25($shopId, $apiKey);
        $result->httpCode = $reviewsResponse->getHttpCode();

        if ($reviewsResponse->isSuccess()) {
            try {
                $reviewsParsed = $this->xmlParser->parse($reviewsResponse->getBody());
                $combinedParsed->hasReviews = $reviewsParsed->hasReviews;
                $combinedParsed->reviews = $reviewsParsed->reviews;
                $result->components['reviews'] = 'success';

                // Fill in shop info if not from stars
                if ($combinedParsed->shopId === null) {
                    $combinedParsed->shopId = $reviewsParsed->shopId;
                    $combinedParsed->shopName = $reviewsParsed->shopName;
                    $combinedParsed->profileUrl = $reviewsParsed->profileUrl;
                    $combinedParsed->shopUrl = $reviewsParsed->shopUrl;
                }
            } catch (XmlParseException $e) {
                $result->success = false;
                $result->components['reviews'] = 'failed';
                $result->error = 'XML parse error (reviews): ' . $e->getMessage();
                return $result;
            }
        } else {
            $result->success = false;
            $result->components['reviews'] = 'failed';
            $result->error = $reviewsResponse->getError() ?? "HTTP {$reviewsResponse->getHttpCode()}";
            return $result;
        }

        // Save combined data
        $this->saveData($combinedParsed, $result);
        $result->success = true;
        $result->partial = $result->components['summary'] !== 'success';

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
            $result->components['summary'] = $parsedResponse->hasSummary ? 'success' : 'unavailable';
            if (!$parsedResponse->hasSummary) {
                $result->success = false;
                $result->error = 'The rating response contained no summary data.';
            }
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
        $db = Db::getInstance();
        if (!$db->execute('START TRANSACTION')) {
            throw new \RuntimeException('Could not start the database transaction.');
        }

        try {
            // A partial API response must never replace the last valid summary.
            if ($parsed->hasSummary) {
                if (!$this->summaryRepository->saveSummary($parsed)) {
                    throw new \RuntimeException('Could not save the rating summary.');
                }
                $result->hasSummary = true;
            }

            if ($parsed->hasReviews) {
                foreach ($parsed->reviews as $review) {
                    $affectedReviews = $this->reviewRepository->saveReview($review);
                    if ($affectedReviews < 0) {
                        throw new \RuntimeException('Could not save review ' . ($review->reviewId ?? 'unknown') . '.');
                    }
                    $result->reviewsUpdated += $affectedReviews;

                    if ($affectedReviews === 1) {
                        if (!$this->metricsRepository->increment('new_review', 'sync')
                            || ($review->isVerified && !$this->metricsRepository->increment('verified_review', 'sync'))
                            || ($review->reviewRatingStars !== null
                                && $review->reviewRatingStars >= 4
                                && !$this->metricsRepository->increment('positive_review', 'sync'))) {
                            throw new \RuntimeException('Could not update aggregate review metrics.');
                        }
                    }
                }
            }

            if (!$db->execute('COMMIT')) {
                throw new \RuntimeException('Could not commit the database transaction.');
            }
        } catch (\Throwable $e) {
            $db->execute('ROLLBACK');
            throw $e;
        }
    }

    /**
     * Cleanup old data based on retention settings
     */
    private function cleanup(): void
    {
        $logRetention = $this->getIntegerConfiguration('LOG_RETENTION_COUNT', 10);
        $dataRetention = $this->getIntegerConfiguration('DATA_RETENTION_DAYS', 365);

        $this->summaryRepository->cleanupOldSummaries($logRetention);
        $this->syncLogRepository->cleanupOldLogs($logRetention);

        if ($dataRetention > 0) {
            $this->reviewRepository->cleanupOldReviews($dataRetention);
        }
    }

    private function getIntegerConfiguration(string $key, int $default): int
    {
        $value = Configuration::get(ShopVoteReviews::CONFIG_KEYS[$key]);

        return ConfigurationValue::integer($value, $default);
    }

    /**
     * Purge all data (admin action)
     */
    public function purgeAllData(): bool
    {
        $db = Db::getInstance();
        if (!$db->execute('START TRANSACTION')) {
            return false;
        }

        $success = $this->summaryRepository->purgeAll()
            && $this->reviewRepository->purgeAll()
            && $this->syncLogRepository->purgeAll()
            && $this->syncLockRepository->forceReleaseAllLocks()
            && $this->metricsRepository->purgeAll()
            && Configuration::updateValue(ShopVoteReviews::CONFIG_KEYS['LAST_FETCH'], '')
            && Configuration::updateValue(ShopVoteReviews::CONFIG_KEYS['LAST_FETCH_STATUS'], '')
            && Configuration::updateValue(ShopVoteReviews::CONFIG_KEYS['LAST_ERROR'], '')
            && Configuration::updateValue(ShopVoteReviews::CONFIG_KEYS['LAST_ERROR_TIME'], '');

        if (!$success || !$db->execute('COMMIT')) {
            $db->execute('ROLLBACK');

            return false;
        }

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
