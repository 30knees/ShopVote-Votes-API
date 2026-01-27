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

        // Get services
        $syncService = $this->module->get('shopvote.service.sync');
        $configService = $this->module->get('shopvote.service.configuration');

        // Validate token
        $token = Tools::getValue('token', '');

        if (empty($token)) {
            $this->sendResponse([
                'success' => false,
                'error' => 'Missing token parameter.',
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

        $statusCode = $result->success ? 200 : ($result->skipped ? 429 : 500);

        $this->sendResponse($result->toArray(), $statusCode);
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
