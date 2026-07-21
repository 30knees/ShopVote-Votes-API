<?php
/**
 * Upgrade ShopVote Reviews from 1.0.x to 1.1.0.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_0($module): bool
{
    $db = Db::getInstance();
    $reviewTable = _DB_PREFIX_ . 'shopvote_review';

    $reviewColumns = $db->executeS("SHOW COLUMNS FROM `{$reviewTable}` LIKE 'first_seen_at'");
    if ($reviewColumns === false) {
        return false;
    }
    if ($reviewColumns === []) {
        if (!$db->execute("ALTER TABLE `{$reviewTable}` ADD `first_seen_at` DATETIME DEFAULT NULL AFTER `fetched_at`, ADD `last_seen_at` DATETIME DEFAULT NULL AFTER `first_seen_at`")) {
            return false;
        }
        if (!$db->execute("UPDATE `{$reviewTable}` SET `first_seen_at` = `fetched_at`, `last_seen_at` = `fetched_at`")) {
            return false;
        }
        if (!$db->execute("ALTER TABLE `{$reviewTable}` MODIFY `first_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, MODIFY `last_seen_at` DATETIME NOT NULL, ADD INDEX `idx_last_seen_at` (`last_seen_at`)")) {
            return false;
        }
    }

    $answerTable = _DB_PREFIX_ . 'shopvote_review_answer';
    $answerIndexes = $db->executeS("SHOW INDEX FROM `{$answerTable}` WHERE Key_name = 'idx_review_shop'");
    if ($answerIndexes === false) {
        return false;
    }
    if ($answerIndexes === []) {
        if (!$db->execute("ALTER TABLE `{$answerTable}` ADD INDEX `idx_review_shop` (`review_id`, `id_shop`)")) {
            return false;
        }
    }

    $metricSql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'shopvote_metric_daily` (
        `id_metric` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `metric_date` DATE NOT NULL,
        `event_name` VARCHAR(32) NOT NULL,
        `placement` VARCHAR(32) NOT NULL,
        `event_count` INT(11) UNSIGNED NOT NULL DEFAULT 0,
        `id_shop` INT(11) UNSIGNED NOT NULL DEFAULT 1,
        PRIMARY KEY (`id_metric`),
        UNIQUE KEY `uk_metric_shop_date_event_placement` (`id_shop`, `metric_date`, `event_name`, `placement`),
        INDEX `idx_metric_date` (`metric_date`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';

    if (!$db->execute($metricSql)) {
        return false;
    }

    foreach ([
        'displayNav1',
        'displayProductAdditionalInfo',
        'displayCheckoutSummaryTop',
        'displayOrderConfirmation',
    ] as $hook) {
        if (!$module->registerHook($hook)) {
            return false;
        }
    }

    $defaults = [
        'DISPLAY_HOME' => true,
        'DISPLAY_SIDEBAR' => true,
        'DISPLAY_PRODUCT' => false,
        'DISPLAY_CHECKOUT' => false,
        'EASYREVIEWS_ENABLED' => false,
        'EASYREVIEWS_SCRIPT_URL' => '',
        'EASYREVIEWS_TOKEN' => '',
        'EASYREVIEWS_OPTIONS' => '{}',
        'PRODUCT_REVIEWS_ENABLED' => false,
        'EVENT_SECRET' => ShopVoteReviews::generateCronToken(),
    ];

    foreach ($defaults as $key => $value) {
        $configurationKey = ShopVoteReviews::CONFIG_KEYS[$key];
        if (!Configuration::hasKey($configurationKey) && !Configuration::updateValue($configurationKey, $value)) {
            return false;
        }
    }

    return true;
}
