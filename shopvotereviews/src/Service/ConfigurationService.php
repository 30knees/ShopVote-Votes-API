<?php
/**
 * ShopVote Reviews - Configuration Service
 *
 * Handles module configuration operations.
 */

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Service;

use Configuration;
use ShopVoteReviews;

class ConfigurationService
{
    private EasyReviewsSnippetParser $easyReviewsSnippetParser;

    public function __construct(EasyReviewsSnippetParser $easyReviewsSnippetParser)
    {
        $this->easyReviewsSnippetParser = $easyReviewsSnippetParser;
    }

    /**
     * Get all configuration values
     */
    public function getAll(): array
    {
        $config = [];

        foreach (ShopVoteReviews::CONFIG_KEYS as $key => $configKey) {
            $value = Configuration::get($configKey);

            // Mask sensitive values
            if (in_array($key, ['API_KEY', 'EASYREVIEWS_TOKEN'], true) && !empty($value)) {
                $config[$key] = ShopVoteReviews::maskApiKey($value);
                $config[$key . '_SET'] = true;
            } elseif ($key === 'CRON_TOKEN' && !empty($value)) {
                $config[$key] = ShopVoteReviews::maskApiKey($value);
                $config[$key . '_SET'] = true;
            } else {
                $config[$key] = $value;
            }
        }

        return $config;
    }

    /**
     * Get a single configuration value
     */
    public function get(string $key): mixed
    {
        $configKey = ShopVoteReviews::CONFIG_KEYS[$key] ?? null;

        if ($configKey === null) {
            return null;
        }

        return Configuration::get($configKey);
    }

    /**
     * Update configuration values
     */
    public function update(array $values): array
    {
        $errors = [];

        foreach ($values as $key => $value) {
            $configKey = ShopVoteReviews::CONFIG_KEYS[$key] ?? null;

            if ($configKey === null) {
                continue;
            }

            // Validate specific fields
            $validationError = $this->validate($key, $value);
            if ($validationError !== null) {
                $errors[$key] = $validationError;
                continue;
            }

            // Skip empty API key updates (keep existing)
            if ($key === 'API_KEY' && empty($value)) {
                continue;
            }

            if (!Configuration::updateValue($configKey, $value)) {
                $errors[$key] = 'The setting could not be saved.';
            }
        }

        return $errors;
    }

    public function importEasyReviewsSnippet(string $snippet): void
    {
        $parsed = $this->easyReviewsSnippetParser->parse($snippet);

        $saved = Configuration::updateValue(ShopVoteReviews::CONFIG_KEYS['EASYREVIEWS_SCRIPT_URL'], $parsed['script_url'])
            && Configuration::updateValue(ShopVoteReviews::CONFIG_KEYS['EASYREVIEWS_TOKEN'], $parsed['token'])
            && Configuration::updateValue(
            ShopVoteReviews::CONFIG_KEYS['EASYREVIEWS_OPTIONS'],
            json_encode($parsed['options'], JSON_UNESCAPED_SLASHES)
        );

        if (!$saved) {
            throw new \RuntimeException('The EasyReviews settings could not be saved.');
        }
    }

    /**
     * Validate a configuration value
     */
    private function validate(string $key, $value): ?string
    {
        switch ($key) {
            case 'SHOP_ID':
                if (!empty($value) && (strlen((string) $value) > 64 || !preg_match('/^[a-zA-Z0-9_-]+$/', $value))) {
                    return 'Shop ID contains invalid characters.';
                }
                break;

            case 'API_KEY':
                if (!empty($value) && (strlen((string) $value) > 256 || preg_match('/[\x00-\x1F\x7F]/', (string) $value))) {
                    return 'API key contains invalid characters.';
                }
                break;

            case 'MIN_INTERVAL':
                $intValue = (int) $value;
                if ($intValue < 60) {
                    return 'Minimum interval must be at least 60 seconds.';
                }
                if ($intValue > 86400) {
                    return 'Minimum interval cannot exceed 24 hours.';
                }
                break;

            case 'REVIEWS_TO_SHOW':
                $intValue = (int) $value;
                if ($intValue < 1 || $intValue > 25) {
                    return 'Reviews to show must be between 1 and 25.';
                }
                break;

            case 'EXCERPT_LENGTH':
                $intValue = (int) $value;
                if ($intValue < 0 || $intValue > 1000) {
                    return 'Excerpt length must be between 0 and 1000.';
                }
                break;

            case 'DATA_RETENTION_DAYS':
                $intValue = (int) $value;
                if ($intValue < 0) {
                    return 'Data retention days cannot be negative.';
                }
                break;

            case 'LOG_RETENTION_COUNT':
                $intValue = (int) $value;
                if ($intValue < 1 || $intValue > 100) {
                    return 'Log retention count must be between 1 and 100.';
                }
                break;

            case 'PREFERRED_MODE':
                if (!array_key_exists($value, ShopVoteReviews::API_MODES)) {
                    return 'Invalid API mode selected.';
                }
                break;
        }

        return null;
    }

    /**
     * Rotate cron token
     */
    public function rotateCronToken(): string
    {
        $newToken = ShopVoteReviews::generateCronToken();
        Configuration::updateValue(ShopVoteReviews::CONFIG_KEYS['CRON_TOKEN'], $newToken);

        return $newToken;
    }

    /**
     * Get cron token (unmasked, for internal use)
     */
    public function getCronToken(): string
    {
        return Configuration::get(ShopVoteReviews::CONFIG_KEYS['CRON_TOKEN']) ?: '';
    }

    /**
     * Validate cron token
     */
    public function validateCronToken(string $token): bool
    {
        $storedToken = $this->getCronToken();

        if (empty($storedToken) || empty($token)) {
            return false;
        }

        return hash_equals($storedToken, $token);
    }

    /**
     * Get API key (unmasked, for internal use)
     */
    public function getApiKey(): string
    {
        return Configuration::get(ShopVoteReviews::CONFIG_KEYS['API_KEY']) ?: '';
    }

    /**
     * Check if module is enabled
     */
    public function isEnabled(): bool
    {
        return (bool) Configuration::get(ShopVoteReviews::CONFIG_KEYS['ENABLED']);
    }

    /**
     * Check if module is configured
     */
    public function isConfigured(): bool
    {
        $shopId = Configuration::get(ShopVoteReviews::CONFIG_KEYS['SHOP_ID']);
        $apiKey = Configuration::get(ShopVoteReviews::CONFIG_KEYS['API_KEY']);

        return !empty($shopId) && !empty($apiKey);
    }
}
