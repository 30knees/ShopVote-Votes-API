<?php
/**
 * ShopVote Reviews - Installer
 *
 * Handles module installation, database creation, and hooks registration.
 */

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Install;

use Configuration;
use Db;
use Module;
use ShopVoteReviews;
use Tab;
use Language;

class Installer
{
    /** @var ShopVoteReviews */
    private $module;

    /** @var array Hooks to register */
    private const HOOKS = [
        'displayHeader',
        'displayFooter',
        'displayHome',
        'displayLeftColumn',
        'displayRightColumn',
        'actionFrontControllerSetMedia',
        'moduleRoutes',
    ];

    /** @var array Default configuration values */
    private const DEFAULT_CONFIG = [
        'ENABLED' => false,
        'SHOP_ID' => '',
        'API_KEY' => '',
        'PREFERRED_MODE' => 'last25ext',
        'MIN_INTERVAL' => 300, // 5 minutes
        'REVIEWS_TO_SHOW' => 5,
        'SHOW_REVIEWER_NAME' => true,
        'EXCERPT_LENGTH' => 200,
        'SHOW_RESPONSES' => true,
        'DATA_RETENTION_DAYS' => 365,
        'LOG_RETENTION_COUNT' => 10,
        'CRON_TOKEN' => '',
        'LAST_FETCH' => '',
        'LAST_FETCH_STATUS' => '',
        'LAST_ERROR' => '',
        'LAST_ERROR_TIME' => '',
        'ENABLE_JSONLD' => true,
        'DISPLAY_HEADER' => false,
        'DISPLAY_FOOTER' => true,
    ];

    public function __construct(ShopVoteReviews $module)
    {
        $this->module = $module;
    }

    /**
     * Install the module
     */
    public function install(): bool
    {
        return $this->createTables()
            && $this->registerHooks()
            && $this->installConfiguration()
            && $this->installTab();
    }

    /**
     * Uninstall the module
     */
    public function uninstall(): bool
    {
        return $this->dropTables()
            && $this->uninstallConfiguration()
            && $this->uninstallTab();
    }

    /**
     * Create database tables
     */
    private function createTables(): bool
    {
        $sql = [];

        // Shop summary table
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'shopvote_shop_summary` (
            `id_summary` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `shop_id` VARCHAR(64) NOT NULL,
            `shop_name` VARCHAR(255) DEFAULT NULL,
            `rating_value_stars` DECIMAL(3,2) DEFAULT NULL,
            `rating_value_score` DECIMAL(5,2) DEFAULT NULL,
            `rating_word` VARCHAR(64) DEFAULT NULL,
            `ratings_count` INT(11) UNSIGNED DEFAULT 0,
            `ratings_positive` INT(11) UNSIGNED DEFAULT 0,
            `ratings_neutral` INT(11) UNSIGNED DEFAULT 0,
            `ratings_negative` INT(11) UNSIGNED DEFAULT 0,
            `comments_count` INT(11) UNSIGNED DEFAULT 0,
            `profile_url` VARCHAR(512) DEFAULT NULL,
            `shop_url` VARCHAR(512) DEFAULT NULL,
            `last_vote` DATETIME DEFAULT NULL,
            `fetched_at` DATETIME NOT NULL,
            `id_shop` INT(11) UNSIGNED NOT NULL DEFAULT 1,
            PRIMARY KEY (`id_summary`),
            INDEX `idx_shop_id` (`shop_id`),
            INDEX `idx_fetched_at` (`fetched_at`),
            INDEX `idx_id_shop` (`id_shop`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';

        // Reviews table
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'shopvote_review` (
            `id_review` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `review_id` VARCHAR(64) NOT NULL,
            `review_url` VARCHAR(512) DEFAULT NULL,
            `review_date` DATETIME DEFAULT NULL,
            `reviewer` VARCHAR(255) DEFAULT NULL,
            `review_rating_stars` DECIMAL(3,2) DEFAULT NULL,
            `review_text` TEXT DEFAULT NULL,
            `is_verified` TINYINT(1) UNSIGNED DEFAULT 0,
            `fetched_at` DATETIME NOT NULL,
            `id_shop` INT(11) UNSIGNED NOT NULL DEFAULT 1,
            PRIMARY KEY (`id_review`),
            UNIQUE KEY `uk_review_id_shop` (`review_id`, `id_shop`),
            INDEX `idx_review_date` (`review_date`),
            INDEX `idx_fetched_at` (`fetched_at`),
            INDEX `idx_id_shop` (`id_shop`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';

        // Review answers table
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'shopvote_review_answer` (
            `id_answer` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `review_id` VARCHAR(64) NOT NULL,
            `answer_type` VARCHAR(32) NOT NULL,
            `answer_date` DATETIME DEFAULT NULL,
            `answer_text` TEXT DEFAULT NULL,
            `id_shop` INT(11) UNSIGNED NOT NULL DEFAULT 1,
            PRIMARY KEY (`id_answer`),
            INDEX `idx_review_id` (`review_id`),
            INDEX `idx_id_shop` (`id_shop`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';

        // Sync logs table
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'shopvote_sync_log` (
            `id_log` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `sync_time` DATETIME NOT NULL,
            `sync_function` VARCHAR(32) NOT NULL,
            `status` VARCHAR(32) NOT NULL,
            `http_code` INT(5) DEFAULT NULL,
            `reviews_updated` INT(11) UNSIGNED DEFAULT 0,
            `message` TEXT DEFAULT NULL,
            `id_shop` INT(11) UNSIGNED NOT NULL DEFAULT 1,
            PRIMARY KEY (`id_log`),
            INDEX `idx_sync_time` (`sync_time`),
            INDEX `idx_id_shop` (`id_shop`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';

        // Sync lock table
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'shopvote_sync_lock` (
            `id_lock` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `lock_key` VARCHAR(64) NOT NULL,
            `locked_at` DATETIME NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `id_shop` INT(11) UNSIGNED NOT NULL DEFAULT 1,
            PRIMARY KEY (`id_lock`),
            UNIQUE KEY `uk_lock_key_shop` (`lock_key`, `id_shop`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Drop database tables
     */
    private function dropTables(): bool
    {
        $tables = [
            'shopvote_shop_summary',
            'shopvote_review',
            'shopvote_review_answer',
            'shopvote_sync_log',
            'shopvote_sync_lock',
        ];

        foreach ($tables as $table) {
            Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . $table . '`');
        }

        return true;
    }

    /**
     * Register module hooks
     */
    private function registerHooks(): bool
    {
        foreach (self::HOOKS as $hook) {
            if (!$this->module->registerHook($hook)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Install default configuration
     */
    private function installConfiguration(): bool
    {
        foreach (self::DEFAULT_CONFIG as $key => $value) {
            $configKey = ShopVoteReviews::CONFIG_KEYS[$key] ?? null;
            if ($configKey === null) {
                continue;
            }

            // Generate cron token on install
            if ($key === 'CRON_TOKEN') {
                $value = ShopVoteReviews::generateCronToken();
            }

            if (!Configuration::updateValue($configKey, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Uninstall configuration
     */
    private function uninstallConfiguration(): bool
    {
        foreach (ShopVoteReviews::CONFIG_KEYS as $configKey) {
            Configuration::deleteByName($configKey);
        }

        return true;
    }

    /**
     * Install admin tab
     */
    private function installTab(): bool
    {
        $tab = new Tab();
        $tab->active = true;
        $tab->class_name = 'AdminShopVoteReviews';
        $tab->module = $this->module->name;
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminParentModulesSf');

        $languages = Language::getLanguages(true);
        foreach ($languages as $language) {
            $tab->name[$language['id_lang']] = 'ShopVote Reviews';
        }

        return $tab->add();
    }

    /**
     * Uninstall admin tab
     */
    private function uninstallTab(): bool
    {
        $tabId = (int) Tab::getIdFromClassName('AdminShopVoteReviews');
        if ($tabId) {
            $tab = new Tab($tabId);
            return $tab->delete();
        }

        return true;
    }
}
