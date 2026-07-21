<?php
/**
 * Signed public endpoint for aggregate storefront metrics.
 */

declare(strict_types=1);

class ShopVoteReviewsEventModuleFrontController extends ModuleFrontController
{
    public $ajax = true;
    public $ssl = true;

    public function initContent(): void
    {
        parent::initContent();

        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');

        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->respond(405);
        }

        $event = (string) Tools::getValue('event', '');
        $placement = (string) Tools::getValue('placement', '');
        $expires = (int) Tools::getValue('expires', 0);
        $shopId = (int) Tools::getValue('shop', 0);
        $signature = (string) Tools::getValue('signature', '');

        if (!in_array($event, ['widget_view', 'shopvote_profile_click'], true)
            || !in_array($placement, \ShopVote\ShopVoteReviews\Repository\MetricsRepository::PLACEMENTS, true)
            || $shopId !== (int) $this->context->shop->id
            || $expires < time()
            || $expires > time() + 600) {
            $this->respond(400);
        }

        $secret = (string) Configuration::get(ShopVoteReviews::CONFIG_KEYS['EVENT_SECRET']);
        $expected = hash_hmac('sha256', $event . '|' . $placement . '|' . $expires . '|' . $shopId, $secret);
        if ($secret === '' || $signature === '' || !hash_equals($expected, $signature)) {
            $this->respond(403);
        }

        if (function_exists('apcu_add')) {
            $clientAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            $rateKey = 'shopvote_event_' . hash_hmac('sha256', $clientAddress . '|' . $event . '|' . $placement, $secret);
            if (!apcu_add($rateKey, 1, 30)) {
                $this->respond(429);
            }
        }

        $metrics = $this->module->get('shopvote.repository.metrics');
        $this->respond($metrics->increment($event, $placement) ? 204 : 500);
    }

    private function respond(int $statusCode): void
    {
        http_response_code($statusCode);
        exit;
    }
}
