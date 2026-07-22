<?php
/**
 * Upgrade ShopVote Reviews from 1.1.3 to 1.2.0.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_2_0($module): bool
{
    foreach ([
        'RATINGSTARS_ENABLED' => false,
        'RATINGSTARS_CODE' => '',
    ] as $key => $value) {
        $configurationKey = ShopVoteReviews::CONFIG_KEYS[$key];
        if (!Configuration::hasKey($configurationKey)
            && !Configuration::updateValue($configurationKey, $value)) {
            return false;
        }
    }

    return true;
}
