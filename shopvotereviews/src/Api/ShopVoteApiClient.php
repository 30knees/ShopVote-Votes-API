<?php
/**
 * ShopVote Reviews - API Client
 *
 * HTTP client for communicating with the ShopVote VotesAPI.
 */

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Api;

use PrestaShop\PrestaShop\Adapter\LegacyLogger;
use ShopVoteReviews;

class ShopVoteApiClient
{
    /** @var string Base API URL */
    private const API_BASE_URL = 'https://api.shopvote.de/ratings/v1';

    /** @var int Connection timeout in seconds */
    private const CONNECT_TIMEOUT = 10;

    /** @var int Request timeout in seconds */
    private const TIMEOUT = 30;

    /** @var int Maximum accepted response size (2 MiB) */
    private const MAX_RESPONSE_BYTES = 2097152;

    /** @var string Provider-specific CA chain used while ShopVote omits its intermediate certificate */
    private const CA_BUNDLE_PATH = __DIR__ . '/../../resources/certs/shopvote-ca-chain.pem';

    /** @var array Valid API functions */
    public const FUNCTIONS = [
        'ratingstars',
        'last25',
        'last25ext',
    ];

    /** @var LegacyLogger|null */
    private $logger;

    public function __construct(?LegacyLogger $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Fetch data from ShopVote API
     *
     * @param string $function API function to call (ratingstars, last25, last25ext)
     * @param string $shopId ShopVote Shop ID
     * @param string $apiKey ShopVote API Key
     *
     * @return ApiResponse
     */
    public function fetch(string $function, string $shopId, string $apiKey): ApiResponse
    {
        if (!in_array($function, self::FUNCTIONS, true)) {
            return new ApiResponse(
                false,
                0,
                null,
                "Invalid API function: {$function}"
            );
        }

        if (!$this->isValidCredential($shopId, 64) || !$this->isValidCredential($apiKey, 256)) {
            return new ApiResponse(false, 0, null, 'Invalid ShopVote credentials.');
        }

        $url = $this->buildUrl($function, $shopId, $apiKey);

        try {
            $response = $this->executeRequest($url);

            // Log the request (with masked API key)
            $this->log(
                'info',
                sprintf(
                    'ShopVote API request: function=%s, shop_id=%s, status=%d',
                    $function,
                    $shopId,
                    $response->getHttpCode()
                )
            );

            return $response;
        } catch (\Exception $e) {
            $maskedUrl = $this->buildUrl($function, $shopId, ShopVoteReviews::maskApiKey($apiKey));

            $this->log(
                'error',
                sprintf(
                    'ShopVote API error: url=%s, message=%s',
                    $maskedUrl,
                    $e->getMessage()
                )
            );

            return new ApiResponse(
                false,
                0,
                null,
                $e->getMessage()
            );
        }
    }

    /**
     * Fetch rating stars (statistics only)
     */
    public function fetchRatingStars(string $shopId, string $apiKey): ApiResponse
    {
        return $this->fetch('ratingstars', $shopId, $apiKey);
    }

    /**
     * Fetch last 25 reviews
     */
    public function fetchLast25(string $shopId, string $apiKey): ApiResponse
    {
        return $this->fetch('last25', $shopId, $apiKey);
    }

    /**
     * Fetch last 25 reviews with rating summary (extended)
     */
    public function fetchLast25Ext(string $shopId, string $apiKey): ApiResponse
    {
        return $this->fetch('last25ext', $shopId, $apiKey);
    }

    /**
     * Build the API URL
     */
    private function buildUrl(string $function, string $shopId, string $apiKey): string
    {
        return sprintf(
            '%s/%s/%s/%s',
            self::API_BASE_URL,
            rawurlencode($function),
            rawurlencode($shopId),
            rawurlencode($apiKey)
        );
    }

    /**
     * Execute HTTP request using cURL
     */
    private function executeRequest(string $url): ApiResponse
    {
        $ch = curl_init();
        $responseBody = '';
        $responseTooLarge = false;

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Accept: application/xml',
                'User-Agent: PrestaShop-ShopVoteReviews/1.1',
            ],
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$responseBody, &$responseTooLarge): int {
                if (strlen($responseBody) + strlen($chunk) > self::MAX_RESPONSE_BYTES) {
                    $responseTooLarge = true;

                    return 0;
                }

                $responseBody .= $chunk;

                return strlen($chunk);
            },
        ];

        if (is_readable(self::CA_BUNDLE_PATH)) {
            $options[CURLOPT_CAINFO] = self::CA_BUNDLE_PATH;
        }

        curl_setopt_array($ch, $options);

        curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);

        curl_close($ch);

        if ($responseTooLarge) {
            return new ApiResponse(false, $httpCode, null, 'ShopVote response exceeded the 2 MiB limit.');
        }

        if ($errno !== 0) {
            if ($errno === 60) {
                $error = 'TLS certificate chain could not be verified. The remote server may not be sending its intermediate certificate. ' . $error;
            }

            return new ApiResponse(
                false,
                0,
                null,
                "cURL error ({$errno}): {$error}"
            );
        }

        if ($httpCode !== 200) {
            return new ApiResponse(
                false,
                $httpCode,
                $responseBody !== '' ? $responseBody : null,
                "HTTP error: {$httpCode}"
            );
        }

        if ($responseBody === '') {
            return new ApiResponse(false, $httpCode, null, 'ShopVote returned an empty response.');
        }

        return new ApiResponse(
            true,
            $httpCode,
            $responseBody !== '' ? $responseBody : null,
            null
        );
    }

    private function isValidCredential(string $value, int $maxLength): bool
    {
        return $value !== ''
            && strlen($value) <= $maxLength
            && !preg_match('/[\x00-\x1F\x7F]/', $value);
    }

    /**
     * Log a message
     */
    private function log(string $level, string $message): void
    {
        if ($this->logger === null) {
            return;
        }

        switch ($level) {
            case 'error':
                $this->logger->error($message);
                break;
            case 'warning':
                $this->logger->warning($message);
                break;
            case 'info':
            default:
                $this->logger->info($message);
                break;
        }
    }
}
