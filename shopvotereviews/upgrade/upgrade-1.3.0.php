<?php
/**
 * Upgrade ShopVote Reviews from 1.2.3 to 1.3.0.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_3_0($module): bool
{
    $homeHookId = (int) Hook::getIdByName('displayHome');
    if ($homeHookId > 0) {
        while ($module->updatePosition($homeHookId, 0)) {
            // updatePosition moves one place at a time.
        }
    }

    Media::clearCache();
    Tools::clearSmartyCache();

    return true;
}
