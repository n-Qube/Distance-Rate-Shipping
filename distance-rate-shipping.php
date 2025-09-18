<?php
/**
 * Plugin Name: Distance Rate Shipping (DRS)
 * Description: Distance-based shipping calculations with external geocoding support.
 * Version: 0.1.0
 * Author: DRS Contributors
 * Text Domain: drs-distance
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/src/autoload.php';

\DRS\Plugin::instance()->init();
