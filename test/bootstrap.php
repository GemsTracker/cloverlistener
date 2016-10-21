<?php

/**
 * @author Menno Dekker <menno.dekker@erasmusmc.nl>
 */

defined('CONFIG_DIR') || define('CONFIG_DIR', __DIR__ . '/config');

$vendorDir = realpath(dirname(__DIR__) . '/vendor');

if (file_exists($vendorDir)) {
    defined('VENDOR_DIR') || define('VENDOR_DIR', $vendorDir);
} else {
    defined('VENDOR_DIR') || define('VENDOR_DIR', dirname(dirname(dirname(__DIR__))));
}

unset($vendorDir);

defined('GEMS_TIMEZONE') || define('GEMS_TIMEZONE', 'Europe/Amsterdam');

/**
 * Always set the system timezone!
 */
date_default_timezone_set(GEMS_TIMEZONE);

require VENDOR_DIR . '/autoload.php';
