<?php
/**
 * Upgrade ShopVote Reviews from 1.3.0 to 1.3.1.
 *
 * Homepage strip redesign (review tiles): no schema changes, but cached
 * assets and compiled templates must be refreshed.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_3_1($module): bool
{
    Media::clearCache();
    Tools::clearSmartyCache();

    return true;
}
