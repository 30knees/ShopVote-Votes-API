<?php
/**
 * ShopVote Reviews - PrestaShop 8.2.x Module
 *
 * Integrates ShopVote VotesAPI to display shop ratings and reviews.
 *
 * @author ShopVote Integration
 * @copyright 2025
 * @license MIT
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;
use ShopVote\ShopVoteReviews\Install\Installer;

class ShopVoteReviews extends Module implements WidgetInterface
{
    /** @var string Module configuration prefix */
    public const CONFIG_PREFIX = 'SHOPVOTE_';

    /** @var array Configuration keys */
    public const CONFIG_KEYS = [
        'ENABLED' => self::CONFIG_PREFIX . 'ENABLED',
        'SHOP_ID' => self::CONFIG_PREFIX . 'SHOP_ID',
        'API_KEY' => self::CONFIG_PREFIX . 'API_KEY',
        'PREFERRED_MODE' => self::CONFIG_PREFIX . 'PREFERRED_MODE',
        'MIN_INTERVAL' => self::CONFIG_PREFIX . 'MIN_INTERVAL',
        'REVIEWS_TO_SHOW' => self::CONFIG_PREFIX . 'REVIEWS_TO_SHOW',
        'SHOW_REVIEWER_NAME' => self::CONFIG_PREFIX . 'SHOW_REVIEWER_NAME',
        'EXCERPT_LENGTH' => self::CONFIG_PREFIX . 'EXCERPT_LENGTH',
        'SHOW_RESPONSES' => self::CONFIG_PREFIX . 'SHOW_RESPONSES',
        'DATA_RETENTION_DAYS' => self::CONFIG_PREFIX . 'DATA_RETENTION_DAYS',
        'LOG_RETENTION_COUNT' => self::CONFIG_PREFIX . 'LOG_RETENTION_COUNT',
        'CRON_TOKEN' => self::CONFIG_PREFIX . 'CRON_TOKEN',
        'LAST_FETCH' => self::CONFIG_PREFIX . 'LAST_FETCH',
        'LAST_FETCH_STATUS' => self::CONFIG_PREFIX . 'LAST_FETCH_STATUS',
        'LAST_ERROR' => self::CONFIG_PREFIX . 'LAST_ERROR',
        'LAST_ERROR_TIME' => self::CONFIG_PREFIX . 'LAST_ERROR_TIME',
        'ENABLE_JSONLD' => self::CONFIG_PREFIX . 'ENABLE_JSONLD',
        'DISPLAY_HEADER' => self::CONFIG_PREFIX . 'DISPLAY_HEADER',
        'DISPLAY_FOOTER' => self::CONFIG_PREFIX . 'DISPLAY_FOOTER',
    ];

    /** @var array Available API modes */
    public const API_MODES = [
        'last25ext' => 'Last 25 Reviews + Rating Summary (Premium)',
        'last25_ratingstars' => 'Last 25 Reviews + Rating Stars (Separate calls)',
        'ratingstars' => 'Rating Stars Only',
    ];

    public function __construct()
    {
        $this->name = 'shopvotereviews';
        $this->tab = 'advertising_marketing';
        $this->version = '1.0.0';
        $this->author = 'ShopVote Integration';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '8.0.0',
            'max' => '8.99.99',
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('ShopVote Reviews', [], 'Modules.Shopvotereviews.Admin');
        $this->description = $this->trans(
            'Display ShopVote shop ratings and reviews on your store.',
            [],
            'Modules.Shopvotereviews.Admin'
        );
        $this->confirmUninstall = $this->trans(
            'Are you sure you want to uninstall? All stored reviews will be deleted.',
            [],
            'Modules.Shopvotereviews.Admin'
        );
    }

    /**
     * Install the module
     */
    public function install(): bool
    {
        if (!parent::install()) {
            return false;
        }

        $installer = new Installer($this);

        return $installer->install();
    }

    /**
     * Uninstall the module
     */
    public function uninstall(): bool
    {
        $installer = new Installer($this);

        if (!$installer->uninstall()) {
            return false;
        }

        return parent::uninstall();
    }

    /**
     * Get module configuration page content
     */
    public function getContent(): string
    {
        $route = $this->get('router')->generate('shopvote_admin_configuration');

        Tools::redirectAdmin($route);

        return '';
    }

    /**
     * Check if the module is configured
     */
    public function isConfigured(): bool
    {
        return !empty(Configuration::get(self::CONFIG_KEYS['SHOP_ID']))
            && !empty(Configuration::get(self::CONFIG_KEYS['API_KEY']));
    }

    /**
     * Check if the module is enabled
     */
    public function isModuleEnabled(): bool
    {
        return (bool) Configuration::get(self::CONFIG_KEYS['ENABLED']);
    }

    /**
     * Hook: displayHeader - Add CSS and JSON-LD
     */
    public function hookDisplayHeader(array $params): string
    {
        if (!$this->isModuleEnabled() || !$this->isConfigured()) {
            return '';
        }

        $this->context->controller->registerStylesheet(
            'shopvote-reviews-css',
            'modules/' . $this->name . '/views/css/shopvote.css',
            ['media' => 'all', 'priority' => 150]
        );

        $output = '';

        // Add JSON-LD structured data if enabled
        if (Configuration::get(self::CONFIG_KEYS['ENABLE_JSONLD'])) {
            $output .= $this->getJsonLdOutput();
        }

        // Display header rating snippet if enabled
        if (Configuration::get(self::CONFIG_KEYS['DISPLAY_HEADER'])) {
            $output .= $this->renderWidget('rating_snippet', []);
        }

        return $output;
    }

    /**
     * Hook: displayFooter - Show footer rating badge
     */
    public function hookDisplayFooter(array $params): string
    {
        if (!$this->isModuleEnabled() || !$this->isConfigured()) {
            return '';
        }

        if (!Configuration::get(self::CONFIG_KEYS['DISPLAY_FOOTER'])) {
            return '';
        }

        return $this->renderWidget('rating_badge', []);
    }

    /**
     * Hook: displayHome - Show reviews on homepage
     */
    public function hookDisplayHome(array $params): string
    {
        if (!$this->isModuleEnabled() || !$this->isConfigured()) {
            return '';
        }

        return $this->renderWidget('reviews_block', []);
    }

    /**
     * Hook: displayLeftColumn
     */
    public function hookDisplayLeftColumn(array $params): string
    {
        if (!$this->isModuleEnabled() || !$this->isConfigured()) {
            return '';
        }

        return $this->renderWidget('rating_sidebar', []);
    }

    /**
     * Hook: displayRightColumn
     */
    public function hookDisplayRightColumn(array $params): string
    {
        return $this->hookDisplayLeftColumn($params);
    }

    /**
     * Hook: actionFrontControllerSetMedia - Register assets
     */
    public function hookActionFrontControllerSetMedia(array $params): void
    {
        if (!$this->isModuleEnabled() || !$this->isConfigured()) {
            return;
        }

        $this->context->controller->registerStylesheet(
            'shopvote-reviews-css',
            'modules/' . $this->name . '/views/css/shopvote.css',
            ['media' => 'all', 'priority' => 150]
        );
    }

    /**
     * Hook: moduleRoutes - Register module routes
     */
    public function hookModuleRoutes(array $params): array
    {
        return [
            'module-shopvotereviews-cron' => [
                'controller' => 'cron',
                'rule' => 'module/shopvotereviews/cron',
                'keywords' => [],
                'params' => [
                    'fc' => 'module',
                    'module' => 'shopvotereviews',
                    'controller' => 'cron',
                ],
            ],
        ];
    }

    /**
     * WidgetInterface: Render widget
     */
    public function renderWidget($hookName, array $configuration): string
    {
        if (!$this->isModuleEnabled() || !$this->isConfigured()) {
            return '';
        }

        $templateFile = $this->getWidgetTemplate($hookName);
        if (!$templateFile) {
            return '';
        }

        $this->smarty->assign($this->getWidgetVariables($hookName, $configuration));

        return $this->fetch($templateFile);
    }

    /**
     * WidgetInterface: Get widget variables
     */
    public function getWidgetVariables($hookName, array $configuration): array
    {
        $summaryRepository = $this->get('shopvote.repository.shop_summary');
        $reviewRepository = $this->get('shopvote.repository.review');

        $summary = $summaryRepository->getLatestSummary();
        $reviewsToShow = (int) Configuration::get(self::CONFIG_KEYS['REVIEWS_TO_SHOW']) ?: 5;
        $reviews = $reviewRepository->getLatestReviews($reviewsToShow);

        $showReviewerName = (bool) Configuration::get(self::CONFIG_KEYS['SHOW_REVIEWER_NAME']);
        $excerptLength = (int) Configuration::get(self::CONFIG_KEYS['EXCERPT_LENGTH']) ?: 200;
        $showResponses = (bool) Configuration::get(self::CONFIG_KEYS['SHOW_RESPONSES']);

        // Process reviews for display
        $processedReviews = [];
        foreach ($reviews as $review) {
            $processedReview = $review;

            if (!$showReviewerName) {
                $processedReview['reviewer'] = $this->anonymizeReviewer($review['reviewer'] ?? null);
            }

            $reviewText = $review['review_text'] ?? '';
            if ($excerptLength > 0 && mb_strlen($reviewText) > $excerptLength) {
                $processedReview['review_text_excerpt'] = mb_substr($reviewText, 0, $excerptLength) . '...';
                $processedReview['has_more'] = true;
            } else {
                $processedReview['review_text_excerpt'] = $reviewText;
                $processedReview['has_more'] = false;
            }

            if ($showResponses && !empty($review['review_id'])) {
                $processedReview['answers'] = $reviewRepository->getAnswersByReviewId($review['review_id']);
            } else {
                $processedReview['answers'] = [];
            }

            $processedReviews[] = $processedReview;
        }

        return [
            'shopvote_summary' => $summary,
            'shopvote_reviews' => $processedReviews,
            'shopvote_show_reviewer_name' => $showReviewerName,
            'shopvote_show_responses' => $showResponses,
            'shopvote_has_data' => !empty($summary),
            'shopvote_stars_html' => $this->generateStarsHtml($summary['rating_value_stars'] ?? 0),
            'shopvote_profile_url' => $summary['profile_url'] ?? '',
            'shopvote_attribution' => 'Quelle: ShopVote.de',
        ];
    }

    /**
     * Get the template file for a widget
     */
    private function getWidgetTemplate(string $hookName): ?string
    {
        $templateMap = [
            'rating_snippet' => 'views/templates/hook/rating_snippet.tpl',
            'rating_badge' => 'views/templates/hook/rating_badge.tpl',
            'rating_sidebar' => 'views/templates/hook/rating_sidebar.tpl',
            'reviews_block' => 'views/templates/hook/reviews_block.tpl',
            'displayHome' => 'views/templates/hook/reviews_block.tpl',
            'displayLeftColumn' => 'views/templates/hook/rating_sidebar.tpl',
            'displayRightColumn' => 'views/templates/hook/rating_sidebar.tpl',
            'displayFooter' => 'views/templates/hook/rating_badge.tpl',
        ];

        $template = $templateMap[$hookName] ?? null;

        if ($template) {
            return 'module:' . $this->name . '/' . $template;
        }

        return null;
    }

    /**
     * Generate JSON-LD structured data for AggregateRating
     */
    private function getJsonLdOutput(): string
    {
        $summaryRepository = $this->get('shopvote.repository.shop_summary');
        $summary = $summaryRepository->getLatestSummary();

        if (empty($summary) || empty($summary['rating_value_stars'])) {
            return '';
        }

        $shopName = Configuration::get('PS_SHOP_NAME');
        $shopUrl = $this->context->link->getPageLink('index', true);

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $shopName,
            'url' => $shopUrl,
            'aggregateRating' => [
                '@type' => 'AggregateRating',
                'ratingValue' => number_format((float) $summary['rating_value_stars'], 1),
                'bestRating' => '5',
                'worstRating' => '1',
                'ratingCount' => (int) $summary['ratings_count'],
                'reviewCount' => (int) $summary['comments_count'],
            ],
        ];

        return '<script type="application/ld+json">' . json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    }

    /**
     * Generate HTML for star rating display
     */
    private function generateStarsHtml(float $rating): string
    {
        $fullStars = (int) floor($rating);
        $hasHalfStar = ($rating - $fullStars) >= 0.5;
        $emptyStars = 5 - $fullStars - ($hasHalfStar ? 1 : 0);

        $html = '<span class="shopvote-stars">';

        for ($i = 0; $i < $fullStars; $i++) {
            $html .= '<span class="shopvote-star shopvote-star-full">★</span>';
        }

        if ($hasHalfStar) {
            $html .= '<span class="shopvote-star shopvote-star-half">★</span>';
        }

        for ($i = 0; $i < $emptyStars; $i++) {
            $html .= '<span class="shopvote-star shopvote-star-empty">☆</span>';
        }

        $html .= '</span>';

        return $html;
    }

    /**
     * Anonymize reviewer name for privacy
     */
    private function anonymizeReviewer(?string $name): string
    {
        if (empty($name)) {
            return $this->trans('Anonymous', [], 'Modules.Shopvotereviews.Shop');
        }

        $parts = explode(' ', $name);
        $first = $parts[0] ?? '';

        $length = mb_strlen($first);
        if ($length > 1) {
            return mb_substr($first, 0, 1) . str_repeat('*', $length - 1);
        }

        return $first . '.';
    }

    /**
     * Mask API key for display (shows first 4 and last 4 characters)
     */
    public static function maskApiKey(string $apiKey): string
    {
        if (strlen($apiKey) <= 8) {
            return str_repeat('*', strlen($apiKey));
        }

        return substr($apiKey, 0, 4) . str_repeat('*', strlen($apiKey) - 8) . substr($apiKey, -4);
    }

    /**
     * Generate a secure cron token
     */
    public static function generateCronToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
