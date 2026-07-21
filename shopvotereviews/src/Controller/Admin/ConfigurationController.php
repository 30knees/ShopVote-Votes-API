<?php
/**
 * ShopVote Reviews - Admin Configuration Controller
 *
 * Handles the module configuration in the back office.
 */

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Controller\Admin;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use ShopVote\ShopVoteReviews\Service\SyncService;
use ShopVote\ShopVoteReviews\Service\ConfigurationService;
use ShopVote\ShopVoteReviews\Repository\ShopSummaryRepository;
use ShopVote\ShopVoteReviews\Repository\ReviewRepository;
use ShopVote\ShopVoteReviews\Repository\SyncLogRepository;
use ShopVote\ShopVoteReviews\Repository\MetricsRepository;
use ShopVoteReviews;

class ConfigurationController extends FrameworkBundleAdminController
{
    /** @var SyncService */
    private $syncService;

    /** @var ConfigurationService */
    private $configurationService;

    /** @var ShopSummaryRepository */
    private $summaryRepository;

    /** @var ReviewRepository */
    private $reviewRepository;

    /** @var SyncLogRepository */
    private $syncLogRepository;

    /** @var MetricsRepository */
    private $metricsRepository;

    public function __construct(
        SyncService $syncService,
        ConfigurationService $configurationService,
        ShopSummaryRepository $summaryRepository,
        ReviewRepository $reviewRepository,
        SyncLogRepository $syncLogRepository,
        MetricsRepository $metricsRepository
    ) {
        $this->syncService = $syncService;
        $this->configurationService = $configurationService;
        $this->summaryRepository = $summaryRepository;
        $this->reviewRepository = $reviewRepository;
        $this->syncLogRepository = $syncLogRepository;
        $this->metricsRepository = $metricsRepository;
    }

    /**
     * Main configuration page
     *
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     */
    public function index(Request $request): Response
    {
        return $this->renderConfigurationPage();
    }

    /**
     * Save configuration
     *
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function save(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('shopvote_configuration', $request->request->get('_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token. Please refresh the page.', 'Modules.Shopvotereviews.Admin'));

            return $this->redirectToRoute('shopvote_admin_configuration');
        }

        $formData = [
            'ENABLED' => $request->request->getBoolean('enabled'),
            'SHOP_ID' => $request->request->get('shop_id', ''),
            'API_KEY' => $request->request->get('api_key', ''),
            'PREFERRED_MODE' => $request->request->get('preferred_mode', 'last25ext'),
            'MIN_INTERVAL' => $request->request->getInt('min_interval', 300),
            'REVIEWS_TO_SHOW' => $request->request->getInt('reviews_to_show', 5),
            'SHOW_REVIEWER_NAME' => $request->request->getBoolean('show_reviewer_name'),
            'EXCERPT_LENGTH' => $request->request->getInt('excerpt_length', 200),
            'SHOW_RESPONSES' => $request->request->getBoolean('show_responses'),
            'DATA_RETENTION_DAYS' => $request->request->getInt('data_retention_days', 365),
            'LOG_RETENTION_COUNT' => $request->request->getInt('log_retention_count', 10),
            'ENABLE_JSONLD' => $request->request->getBoolean('enable_jsonld'),
            'DISPLAY_HEADER' => $request->request->getBoolean('display_header'),
            'DISPLAY_FOOTER' => $request->request->getBoolean('display_footer'),
            'DISPLAY_HOME' => $request->request->getBoolean('display_home'),
            'DISPLAY_SIDEBAR' => $request->request->getBoolean('display_sidebar'),
            'DISPLAY_PRODUCT' => $request->request->getBoolean('display_product'),
            'DISPLAY_CHECKOUT' => $request->request->getBoolean('display_checkout'),
            'EASYREVIEWS_ENABLED' => $request->request->getBoolean('easyreviews_enabled'),
            'PRODUCT_REVIEWS_ENABLED' => $request->request->getBoolean('product_reviews_enabled'),
        ];

        $errors = $this->configurationService->update($formData);

        $easyReviewsSnippet = trim((string) $request->request->get('easyreviews_import_code', ''));
        if ($easyReviewsSnippet !== '') {
            try {
                $this->configurationService->importEasyReviewsSnippet($easyReviewsSnippet);
            } catch (\InvalidArgumentException | \RuntimeException $e) {
                $errors['EASYREVIEWS_IMPORT'] = $e->getMessage();
            }
        }

        if (empty($errors)) {
            $this->addFlash('success', $this->trans('Settings saved successfully.', 'Modules.Shopvotereviews.Admin'));
        } else {
            foreach ($errors as $field => $error) {
                $this->addFlash('error', "{$field}: {$error}");
            }
        }

        return $this->redirectToRoute('shopvote_admin_configuration');
    }

    /**
     * Render the configuration page
     */
    private function renderConfigurationPage(): Response
    {
        $config = $this->configurationService->getAll();
        $syncStatus = $this->syncService->getSyncStatus();
        $summary = $this->summaryRepository->getLatestSummary();
        $recentLogs = $this->syncLogRepository->getRecentLogs(10);

        // Generate cron URL
        $cronToken = $this->configurationService->getCronToken();
        $cronUrl = \Context::getContext()->link->getModuleLink(
            'shopvotereviews',
            'cron',
            ['token' => $cronToken],
            true
        );
        $cronEndpoint = \Context::getContext()->link->getModuleLink('shopvotereviews', 'cron', [], true);

        return $this->render('@Modules/shopvotereviews/views/templates/admin/configuration.html.twig', [
            'config' => $config,
            'sync_status' => $syncStatus,
            'summary' => $summary,
            'recent_logs' => $recentLogs,
            'review_health' => $this->reviewRepository->getReviewHealth(),
            'metric_overview' => $this->metricsRepository->getOverview(),
            'growth_summary' => $this->metricsRepository->getDashboard(),
            'api_modes' => ShopVoteReviews::API_MODES,
            'cron_url' => $cronUrl,
            'cron_endpoint' => $cronEndpoint,
            'cron_token' => $cronToken,
            'module_name' => 'shopvotereviews',
        ]);
    }

    /**
     * Manual fetch action
     *
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function fetch(Request $request): JsonResponse
    {
        // Validate CSRF token
        if (!$this->isCsrfTokenValid('shopvote_ajax', $request->request->get('_token'))) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Invalid security token. Please refresh the page.',
            ], 403);
        }

        if (!$this->configurationService->isConfigured()) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Module is not configured.',
            ], 400);
        }

        $force = $request->request->getBoolean('force', false);
        $result = $this->syncService->sync($force);

        return new JsonResponse($result->toArray());
    }

    /**
     * Purge all data action
     *
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))")
     */
    public function purge(Request $request): JsonResponse
    {
        // Validate CSRF token
        if (!$this->isCsrfTokenValid('shopvote_ajax', $request->request->get('_token'))) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Invalid security token. Please refresh the page.',
            ], 403);
        }

        $this->syncService->purgeAllData();

        return new JsonResponse([
            'success' => true,
            'message' => 'All data has been purged.',
        ]);
    }

    /**
     * Rotate cron token action
     *
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function rotateToken(Request $request): JsonResponse
    {
        // Validate CSRF token
        if (!$this->isCsrfTokenValid('shopvote_ajax', $request->request->get('_token'))) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Invalid security token. Please refresh the page.',
            ], 403);
        }

        $newToken = $this->configurationService->rotateCronToken();
        $cronUrl = \Context::getContext()->link->getModuleLink(
            'shopvotereviews',
            'cron',
            ['token' => $newToken],
            true
        );

        return new JsonResponse([
            'success' => true,
            'cron_url' => $cronUrl,
            'token_masked' => ShopVoteReviews::maskApiKey($newToken),
        ]);
    }
}
