<?php
/**
 * ShopVote Reviews - Cron Controller
 *
 * Handles cron endpoint for automated sync.
 */

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Controller\Front;

use ModuleFrontController;
use ShopVote\ShopVoteReviews\Service\SyncService;
use ShopVote\ShopVoteReviews\Service\ConfigurationService;

class CronController extends ModuleFrontController
{
    /** @var bool */
    public $ajax = true;

    /** @var SyncService */
    private $syncService;

    /** @var ConfigurationService */
    private $configurationService;

    public function __construct()
    {
        parent::__construct();

        // Get services from container
        $this->syncService = $this->get('shopvote.service.sync');
        $this->configurationService = $this->get('shopvote.service.configuration');
    }

    /**
     * Initialize controller
     */
    public function init(): void
    {
        parent::init();

        // Disable debug and profiler for cron
        if (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_) {
            // Suppress debug output
        }
    }

    /**
     * Handle cron request
     */
    public function initContent(): void
    {
        parent::initContent();

        header('Content-Type: application/json');

        // Validate token
        $token = \Tools::getValue('token', '');

        if (empty($token)) {
            $this->sendJsonResponse([
                'success' => false,
                'error' => 'Missing token parameter.',
            ], 401);
            return;
        }

        if (!$this->configurationService->validateCronToken($token)) {
            $this->sendJsonResponse([
                'success' => false,
                'error' => 'Invalid token.',
            ], 403);
            return;
        }

        // Check if enabled
        if (!$this->configurationService->isEnabled()) {
            $this->sendJsonResponse([
                'success' => false,
                'error' => 'Module is disabled.',
            ], 400);
            return;
        }

        // Check if configured
        if (!$this->configurationService->isConfigured()) {
            $this->sendJsonResponse([
                'success' => false,
                'error' => 'Module is not configured.',
            ], 400);
            return;
        }

        // Perform sync
        $result = $this->syncService->sync(false);

        $statusCode = $result->success ? 200 : ($result->skipped ? 429 : 500);

        $this->sendJsonResponse($result->toArray(), $statusCode);
    }

    /**
     * Send JSON response and exit
     */
    private function sendJsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
}
