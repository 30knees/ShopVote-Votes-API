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
use ShopVote\ShopVoteReviews\Security\ShopVoteUrlValidator;
use ShopVote\ShopVoteReviews\Support\ConfigurationValue;
use ShopVote\ShopVoteReviews\Support\ReviewerAnonymizer;

class ShopVoteReviews extends Module implements WidgetInterface
{
    /** @var array<string, array> Request-level widget data cache */
    private array $widgetDataCache = [];

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
        'DISPLAY_HOME' => self::CONFIG_PREFIX . 'DISPLAY_HOME',
        'DISPLAY_SIDEBAR' => self::CONFIG_PREFIX . 'DISPLAY_SIDEBAR',
        'DISPLAY_PRODUCT' => self::CONFIG_PREFIX . 'DISPLAY_PRODUCT',
        'DISPLAY_CHECKOUT' => self::CONFIG_PREFIX . 'DISPLAY_CHECKOUT',
        'EASYREVIEWS_ENABLED' => self::CONFIG_PREFIX . 'EASYREVIEWS_ENABLED',
        'EASYREVIEWS_SCRIPT_URL' => self::CONFIG_PREFIX . 'EASYREVIEWS_SCRIPT_URL',
        'EASYREVIEWS_TOKEN' => self::CONFIG_PREFIX . 'EASYREVIEWS_TOKEN',
        'EASYREVIEWS_OPTIONS' => self::CONFIG_PREFIX . 'EASYREVIEWS_OPTIONS',
        'PRODUCT_REVIEWS_ENABLED' => self::CONFIG_PREFIX . 'PRODUCT_REVIEWS_ENABLED',
        'EVENT_SECRET' => self::CONFIG_PREFIX . 'EVENT_SECRET',
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
        $this->version = '1.1.0';
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

    /** Hook: displayHeader - structured data only on the dedicated reviews page. */
    public function hookDisplayHeader(array $params): string
    {
        if (!$this->isModuleEnabled() || !$this->isConfigured()) {
            return '';
        }

        $controllerClass = strtolower(get_class($this->context->controller));
        if (!str_contains($controllerClass, 'shopvotereviewsreviewsmodulefrontcontroller')) {
            return '';
        }

        return Configuration::get(self::CONFIG_KEYS['ENABLE_JSONLD']) ? $this->getJsonLdOutput() : '';
    }

    /** Hook: displayNav1 - visible compact rating. */
    public function hookDisplayNav1(array $params): string
    {
        if (!$this->isModuleEnabled() || !$this->isConfigured()
            || !Configuration::get(self::CONFIG_KEYS['DISPLAY_HEADER'])) {
            return '';
        }

        return $this->renderWidget('rating_snippet', ['placement' => 'header']);
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

        return $this->renderWidget('rating_badge', ['placement' => 'footer']);
    }

    /**
     * Hook: displayHome - Show reviews on homepage
     */
    public function hookDisplayHome(array $params): string
    {
        if (!$this->isModuleEnabled() || !$this->isConfigured()
            || !Configuration::get(self::CONFIG_KEYS['DISPLAY_HOME'])) {
            return '';
        }

        return $this->renderWidget('reviews_block', ['placement' => 'homepage']);
    }

    /**
     * Hook: displayLeftColumn
     */
    public function hookDisplayLeftColumn(array $params): string
    {
        if (!$this->isModuleEnabled() || !$this->isConfigured()
            || !Configuration::get(self::CONFIG_KEYS['DISPLAY_SIDEBAR'])) {
            return '';
        }

        return $this->renderWidget('rating_sidebar', ['placement' => 'sidebar']);
    }

    /**
     * Hook: displayRightColumn
     */
    public function hookDisplayRightColumn(array $params): string
    {
        return $this->hookDisplayLeftColumn($params);
    }

    public function hookDisplayProductAdditionalInfo(array $params): string
    {
        if (!$this->isModuleEnabled() || !$this->isConfigured()
            || !Configuration::get(self::CONFIG_KEYS['DISPLAY_PRODUCT'])) {
            return '';
        }

        return $this->renderWidget('rating_compact', [
            'placement' => 'product',
            'label' => $this->trans('Shop rating', [], 'Modules.Shopvotereviews.Shop'),
        ]);
    }

    public function hookDisplayCheckoutSummaryTop(array $params): string
    {
        if (!$this->isModuleEnabled() || !$this->isConfigured()
            || !Configuration::get(self::CONFIG_KEYS['DISPLAY_CHECKOUT'])) {
            return '';
        }

        return $this->renderWidget('rating_compact', [
            'placement' => 'checkout',
            'label' => $this->trans('Customer shop rating', [], 'Modules.Shopvotereviews.Shop'),
        ]);
    }

    public function hookDisplayOrderConfirmation(array $params): string
    {
        if (!$this->isModuleEnabled()) {
            return '';
        }

        $order = $params['order'] ?? null;
        if (!$order instanceof Order || !Validate::isLoadedObject($order)) {
            return '';
        }

        if ($this->context->customer->isLogged()
            && (int) $order->id_customer !== (int) $this->context->customer->id) {
            return '';
        }

        $metrics = $this->get('shopvote.repository.metrics');
        $metrics->increment('order_confirmation', 'order_confirmation');

        if (!Configuration::get(self::CONFIG_KEYS['EASYREVIEWS_ENABLED'])) {
            return '';
        }

        $scriptUrl = ShopVoteUrlValidator::normalize(
            (string) Configuration::get(self::CONFIG_KEYS['EASYREVIEWS_SCRIPT_URL']),
            true
        );
        $token = (string) Configuration::get(self::CONFIG_KEYS['EASYREVIEWS_TOKEN']);
        if ($scriptUrl === null || !preg_match('/^[A-Za-z0-9_-]{8,256}$/', $token)) {
            return '';
        }

        $customer = new Customer((int) $order->id_customer);
        if (!Validate::isLoadedObject($customer) || !Validate::isEmail($customer->email)) {
            return '';
        }

        $products = [];
        if (Configuration::get(self::CONFIG_KEYS['PRODUCT_REVIEWS_ENABLED'])) {
            foreach ($order->getProducts() as $orderProduct) {
                $product = new Product(
                    (int) $orderProduct['product_id'],
                    false,
                    (int) $this->context->language->id,
                    (int) $this->context->shop->id
                );
                if (!Validate::isLoadedObject($product)) {
                    continue;
                }

                $cover = Product::getCover((int) $product->id);
                $products[] = [
                    'url' => $this->context->link->getProductLink($product, null, null, null, null, null, (int) $orderProduct['product_attribute_id']),
                    'image_url' => $cover
                        ? $this->context->link->getImageLink($product->link_rewrite, $cover['id_image'], 'home_default')
                        : '',
                    'name' => (string) $orderProduct['product_name'],
                    'gtin' => (string) ($orderProduct['product_ean13'] ?? ''),
                    'sku' => (string) ($orderProduct['product_reference'] ?? ''),
                    'brand' => (string) Manufacturer::getNameById((int) $product->id_manufacturer),
                ];
            }
        }

        $options = json_decode((string) Configuration::get(self::CONFIG_KEYS['EASYREVIEWS_OPTIONS']), true);
        $language = strtoupper((string) ($options['language'] ?? $this->context->language->iso_code));
        if (!in_array($language, ['DE', 'EN', 'FR', 'IT', 'NL', 'ES'], true)) {
            $language = 'EN';
        }

        $this->smarty->assign([
            'shopvote_easyreviews_script_url' => $scriptUrl,
            'shopvote_easyreviews_token' => $token,
            'shopvote_easyreviews_language' => $language,
            'shopvote_customer_email' => $customer->email,
            'shopvote_order_reference' => $order->reference,
            'shopvote_order_products' => $products,
        ]);

        $metrics->increment('easyreviews_prompt', 'order_confirmation');

        return $this->fetch('module:' . $this->name . '/views/templates/hook/easyreviews.tpl');
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
        $this->context->controller->registerJavascript(
            'shopvote-reviews-metrics',
            'modules/' . $this->name . '/views/js/metrics.js',
            ['position' => 'bottom', 'priority' => 150]
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
            'module-shopvotereviews-reviews' => [
                'controller' => 'reviews',
                'rule' => 'shop-reviews',
                'keywords' => [],
                'params' => [
                    'fc' => 'module',
                    'module' => 'shopvotereviews',
                    'controller' => 'reviews',
                ],
            ],
            'module-shopvotereviews-event' => [
                'controller' => 'event',
                'rule' => 'module/shopvotereviews/event',
                'keywords' => [],
                'params' => [
                    'fc' => 'module',
                    'module' => 'shopvotereviews',
                    'controller' => 'event',
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
        $configuredLimit = Configuration::get(self::CONFIG_KEYS['REVIEWS_TO_SHOW']);
        $reviewsToShow = isset($configuration['limit'])
            ? max(1, min(25, (int) $configuration['limit']))
            : ConfigurationValue::integer($configuredLimit, 5);
        $fullText = !empty($configuration['full_text']);
        $defaultPlacements = [
            'rating_snippet' => 'header',
            'rating_badge' => 'footer',
            'rating_sidebar' => 'sidebar',
            'rating_compact' => 'product',
            'reviews_block' => 'homepage',
        ];
        $placement = preg_replace(
            '/[^a-z_]/',
            '',
            (string) ($configuration['placement'] ?? ($defaultPlacements[$hookName] ?? 'homepage'))
        ) ?: 'homepage';
        $placementData = $this->buildPlacementVariables($placement, (string) ($configuration['label'] ?? ''));
        $cacheKey = implode(':', [
            (int) $this->context->shop->id,
            (int) $this->context->language->id,
            $reviewsToShow,
            $fullText ? 1 : 0,
        ]);

        if (isset($this->widgetDataCache[$cacheKey])) {
            return array_merge($this->widgetDataCache[$cacheKey], $placementData);
        }

        $summaryRepository = $this->get('shopvote.repository.shop_summary');
        $reviewRepository = $this->get('shopvote.repository.review');
        $summary = $summaryRepository->getLatestSummary();
        $reviews = $reviewRepository->getLatestReviews($reviewsToShow);

        $showReviewerName = (bool) Configuration::get(self::CONFIG_KEYS['SHOW_REVIEWER_NAME']);
        $configuredExcerptLength = Configuration::get(self::CONFIG_KEYS['EXCERPT_LENGTH']);
        $excerptLength = $fullText
            ? 0
            : ConfigurationValue::integer($configuredExcerptLength, 200);
        $showResponses = (bool) Configuration::get(self::CONFIG_KEYS['SHOW_RESPONSES']);
        $reviewIds = array_column($reviews, 'review_id');
        $answersByReview = $showResponses ? $reviewRepository->getAnswersByReviewIds($reviewIds) : [];

        // Process reviews for display
        $processedReviews = [];
        foreach ($reviews as $review) {
            $processedReview = $review;
            $processedReview['review_url'] = ShopVoteUrlValidator::normalize($review['review_url'] ?? null) ?? '';

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

            $processedReview['answers'] = $showResponses && !empty($review['review_id'])
                ? ($answersByReview[$review['review_id']] ?? [])
                : [];

            $processedReviews[] = $processedReview;
        }

        $profileUrl = ShopVoteUrlValidator::normalize($summary['profile_url'] ?? null) ?? '';
        $widgetData = [
            'shopvote_summary' => $summary,
            'shopvote_reviews' => $processedReviews,
            'shopvote_show_reviewer_name' => $showReviewerName,
            'shopvote_show_responses' => $showResponses,
            'shopvote_has_data' => !empty($summary),
            'shopvote_stars_html' => $this->generateStarsHtml($summary['rating_value_stars'] ?? 0),
            'shopvote_profile_url' => $profileUrl,
            'shopvote_reviews_url' => $this->context->link->getModuleLink($this->name, 'reviews', [], true),
            'shopvote_attribution' => 'Quelle: ShopVote.de',
        ];

        $this->widgetDataCache[$cacheKey] = $widgetData;

        return array_merge($widgetData, $placementData);
    }

    private function buildPlacementVariables(string $placement, string $label): array
    {
        $expires = time() + 300;
        $shopId = (int) $this->context->shop->id;
        $secret = (string) Configuration::get(self::CONFIG_KEYS['EVENT_SECRET']);
        $sign = static function (string $event) use ($placement, $expires, $shopId, $secret): string {
            return $secret === ''
                ? ''
                : hash_hmac('sha256', $event . '|' . $placement . '|' . $expires . '|' . $shopId, $secret);
        };

        return [
            'shopvote_placement' => $placement,
            'shopvote_placement_label' => $label,
            'shopvote_metric_endpoint' => $this->context->link->getModuleLink($this->name, 'event', [], true),
            'shopvote_metric_expires' => $expires,
            'shopvote_metric_shop_id' => $shopId,
            'shopvote_view_signature' => $sign('widget_view'),
            'shopvote_click_signature' => $sign('shopvote_profile_click'),
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
            'rating_compact' => 'views/templates/hook/rating_compact.tpl',
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
        $rating = max(0.0, min(5.0, $rating));
        $fullStars = (int) floor($rating);
        $hasHalfStar = ($rating - $fullStars) >= 0.5;
        $emptyStars = 5 - $fullStars - ($hasHalfStar ? 1 : 0);

        $label = $this->trans(
            'Rating: %rating% out of 5 stars',
            ['%rating%' => number_format($rating, 1, '.', '')],
            'Modules.Shopvotereviews.Shop'
        );
        $html = '<span class="shopvote-stars" role="img" aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">';

        for ($i = 0; $i < $fullStars; $i++) {
            $html .= '<span class="shopvote-star shopvote-star-full" aria-hidden="true">★</span>';
        }

        if ($hasHalfStar) {
            $html .= '<span class="shopvote-star shopvote-star-half" aria-hidden="true">★</span>';
        }

        for ($i = 0; $i < $emptyStars; $i++) {
            $html .= '<span class="shopvote-star shopvote-star-empty" aria-hidden="true">☆</span>';
        }

        $html .= '</span>';

        return $html;
    }

    /**
     * Anonymize reviewer name for privacy
     */
    private function anonymizeReviewer(?string $name): string
    {
        return ReviewerAnonymizer::anonymize(
            $name,
            $this->trans('Anonymous', [], 'Modules.Shopvotereviews.Shop')
        );
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
