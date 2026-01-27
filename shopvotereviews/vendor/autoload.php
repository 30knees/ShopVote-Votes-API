<?php
/**
 * ShopVote Reviews - Autoloader
 *
 * PSR-4 compatible autoloader for the module.
 * This file is generated for development purposes.
 * In production, use Composer's autoloader.
 */

spl_autoload_register(function ($class) {
    // Module namespace prefix
    $prefix = 'ShopVote\\ShopVoteReviews\\';

    // Base directory for the namespace prefix
    $baseDir = __DIR__ . '/../src/';

    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // No, move to the next registered autoloader
        return;
    }

    // Get the relative class name
    $relativeClass = substr($class, $len);

    // Replace namespace separators with directory separators
    // and append .php
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});
