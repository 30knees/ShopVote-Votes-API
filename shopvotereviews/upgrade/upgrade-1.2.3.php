<?php
/**
 * Upgrade ShopVote Reviews from 1.2.2 to 1.2.3.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_2_3($module): bool
{
    Media::clearCache();
    Tools::clearSmartyCache();

    return true;
}
