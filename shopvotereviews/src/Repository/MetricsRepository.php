<?php
/**
 * Aggregate, PII-free daily metrics.
 */

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Repository;

use Context;
use Db;

class MetricsRepository
{
    public const EVENTS = [
        'widget_view',
        'shopvote_profile_click',
        'order_confirmation',
        'easyreviews_prompt',
        'new_review',
        'verified_review',
        'positive_review',
    ];

    public const PLACEMENTS = [
        'header',
        'footer',
        'homepage',
        'sidebar',
        'product',
        'checkout',
        'reviews_page',
        'order_confirmation',
        'sync',
    ];

    public function increment(string $event, string $placement, int $amount = 1, ?int $shopId = null): bool
    {
        if (!in_array($event, self::EVENTS, true)
            || !in_array($placement, self::PLACEMENTS, true)
            || $amount < 1
            || $amount > 1000) {
            return false;
        }

        $shopId = $shopId ?? (int) Context::getContext()->shop->id;
        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'shopvote_metric_daily`
                (`metric_date`, `event_name`, `placement`, `event_count`, `id_shop`)
                VALUES (CURDATE(), \'' . pSQL($event) . '\', \'' . pSQL($placement) . '\', ' . (int) $amount . ', ' . (int) $shopId . ')
                ON DUPLICATE KEY UPDATE `event_count` = `event_count` + ' . (int) $amount;

        return Db::getInstance()->execute($sql);
    }

    public function getOverview(int $days = 30, ?int $shopId = null): array
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;
        $days = max(1, min(365, $days));
        $sql = new \DbQuery();
        $sql->select('event_name, placement, SUM(event_count) AS event_count');
        $sql->from('shopvote_metric_daily');
        $sql->where('id_shop = ' . (int) $shopId);
        $sql->where('metric_date >= DATE_SUB(CURDATE(), INTERVAL ' . (int) ($days - 1) . ' DAY)');
        $sql->groupBy('event_name, placement');
        $sql->orderBy('event_name ASC, placement ASC');

        return Db::getInstance()->executeS($sql) ?: [];
    }

    public function getDashboard(int $days = 30, ?int $shopId = null): array
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;
        $totals = array_fill_keys(self::EVENTS, 0);
        foreach ($this->getOverview($days, $shopId) as $row) {
            $totals[$row['event_name']] += (int) $row['event_count'];
        }

        $sql = 'SELECT
                    SUM(IF(event_name = \'order_confirmation\' AND metric_date >= DATE_SUB(CURDATE(), INTERVAL ' . (int) ($days - 1) . ' DAY), event_count, 0)) AS orders_current,
                    SUM(IF(event_name = \'order_confirmation\' AND metric_date >= DATE_SUB(CURDATE(), INTERVAL ' . (int) (($days * 2) - 1) . ' DAY) AND metric_date < DATE_SUB(CURDATE(), INTERVAL ' . (int) ($days - 1) . ' DAY), event_count, 0)) AS orders_previous
                FROM `' . _DB_PREFIX_ . 'shopvote_metric_daily`
                WHERE id_shop = ' . (int) $shopId;
        $periods = Db::getInstance()->getRow($sql) ?: [];

        return [
            'widget_views' => $totals['widget_view'],
            'profile_clicks' => $totals['shopvote_profile_click'],
            'profile_ctr' => $totals['widget_view'] > 0
                ? round(($totals['shopvote_profile_click'] / $totals['widget_view']) * 100, 1)
                : 0.0,
            'orders_current' => (int) ($periods['orders_current'] ?? 0),
            'orders_previous' => (int) ($periods['orders_previous'] ?? 0),
            'easyreviews_prompts' => $totals['easyreviews_prompt'],
            'new_verified_reviews' => $totals['verified_review'],
            'positive_share' => $totals['new_review'] > 0
                ? round(($totals['positive_review'] / $totals['new_review']) * 100, 1)
                : 0.0,
        ];
    }

    public function purgeAll(?int $shopId = null): bool
    {
        $shopId = $shopId ?? (int) Context::getContext()->shop->id;

        return Db::getInstance()->delete('shopvote_metric_daily', 'id_shop = ' . (int) $shopId);
    }
}
