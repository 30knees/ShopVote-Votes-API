<?php
/**
 * ShopVote Reviews - Cron Front Controller (Legacy)
 *
 * Legacy front controller for cron endpoint.
 * URL: /module/shopvotereviews/cron?token=...
 */

declare(strict_types=1);

class ShopVoteReviewsCronModuleFrontController extends ModuleFrontController
{
    /** @var bool */
    public $ajax = true;

    /** @var bool */
    public $ssl = true;

    /**
     * Initialize controller
     */
    public function init(): void
    {
        parent::init();

        // Disable layout for API response
        $this->ajax = true;
    }

    /**
     * Handle cron request
     */
    public function initContent(): void
    {
        parent::initContent();

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');

        // Get services
        $syncService = $this->module->get('shopvote.service.sync');
        $configService = $this->module->get('shopvote.service.configuration');

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        $usesDeprecatedQueryToken = false;
        $token = '';

        if ($method === 'POST' && preg_match('/^Bearer\s+([^\s]+)$/i', trim($authorization), $matches)) {
            $token = $matches[1];
        } elseif ($method === 'GET') {
            $token = (string) Tools::getValue('token', '');
            $usesDeprecatedQueryToken = $token !== '';
        }

        if ($token === '') {
            $this->sendResponse([
                'success' => false,
                'error' => 'Use POST with an Authorization: Bearer header.',
            ], 401);
            return;
        }

        if (!$configService->validateCronToken($token)) {
            $this->sendResponse([
                'success' => false,
                'error' => 'Invalid token.',
            ], 403);
            return;
        }

        if ($usesDeprecatedQueryToken) {
            header('Deprecation: true');
            header('Warning: 299 - "Query-string cron authentication is deprecated and will be removed in version 2.0."');
        }

        // Check if enabled
        if (!$configService->isEnabled()) {
            $this->sendResponse([
                'success' => false,
                'error' => 'Module is disabled.',
            ], 400);
            return;
        }

        // Check if configured
        if (!$configService->isConfigured()) {
            $this->sendResponse([
                'success' => false,
                'error' => 'Module is not configured.',
            ], 400);
            return;
        }

        // Perform sync
        $result = $syncService->sync(false);

        $statusCode = $result->success
            ? 200
            : ($result->locked ? 409 : ($result->skipped ? 429 : 500));

        $response = $result->toArray();
        if ($usesDeprecatedQueryToken) {
            $response['deprecation'] = 'Query-string authentication is deprecated; use an Authorization: Bearer header.';
        }

        $this->sendResponse($response, $statusCode);
    }

    /**
     * Send JSON response and exit
     */
    private function sendResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
