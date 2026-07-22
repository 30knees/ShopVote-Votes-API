<?php
/**
 * Upgrade ShopVote Reviews from 1.1.2 to 1.1.3.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_3($module): bool
{
    foreach ([
        'displayLeftColumnProduct',
        'displayRightColumnProduct',
    ] as $hook) {
        if (!$module->registerHook($hook)) {
            return false;
        }
    }

    foreach ([
        'EASYREVIEWS_HTML_CODE',
        'EASYREVIEWS_JAVASCRIPT_CODE',
    ] as $key) {
        $configurationKey = ShopVoteReviews::CONFIG_KEYS[$key];
        if (!Configuration::hasKey($configurationKey)
            && !Configuration::updateValue($configurationKey, '')) {
            return false;
        }
    }

    return true;
}
