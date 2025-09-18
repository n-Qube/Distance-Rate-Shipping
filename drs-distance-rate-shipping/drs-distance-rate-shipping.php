<?php
/**
 * Plugin Name: Distance Rate Shipping (DRS)
 * Description: Distance based shipping method for WooCommerce.
 * Version: 1.0.0
 * Author: Distance Rate Shipping Contributors
 * Text Domain: drs-distance
 * Domain Path: /languages
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('DRS_DISTANCE_RATE_SHIPPING_FILE')) {
    define('DRS_DISTANCE_RATE_SHIPPING_FILE', __FILE__);
}

$drs_autoload = __DIR__ . '/vendor/autoload.php';

if (is_readable($drs_autoload)) {
    require $drs_autoload;
}

if (! class_exists('DRS\\DistanceRateShipping\\Bootstrap') && is_readable(__DIR__ . '/src/Bootstrap.php')) {
    require __DIR__ . '/src/Bootstrap.php';
}

if (class_exists('DRS\\DistanceRateShipping\\Bootstrap')) {
    $drs_bootstrap = new \DRS\DistanceRateShipping\Bootstrap(DRS_DISTANCE_RATE_SHIPPING_FILE);
    if (method_exists($drs_bootstrap, 'init')) {
        $drs_bootstrap->init();
    }
}
