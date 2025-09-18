<?php
/**
 * Plugin Name: Distance Rate Shipping (DRS)
 * Plugin URI:  https://example.com/
 * Description: Distance based shipping method with global fallback support.
 * Version:     1.0.0
 * Author:      DRS
 * Text Domain: distance-rate-shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'DRS_PLUGIN_FILE' ) ) {
    define( 'DRS_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'DRS_PLUGIN_DIR' ) ) {
    define( 'DRS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'DRS_PLUGIN_URL' ) ) {
    define( 'DRS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Load shipping classes.
 */
function drs_load_shipping_dependencies() {
    if ( ! class_exists( 'WC_Shipping_Method' ) ) {
        return;
    }

    require_once DRS_PLUGIN_DIR . 'src/Shipping/Method.php';
    require_once DRS_PLUGIN_DIR . 'src/Shipping/Zones.php';
}
add_action( 'woocommerce_shipping_init', 'drs_load_shipping_dependencies', 0 );

/**
 * Register the DRS shipping method for zones.
 *
 * @param array $methods Registered shipping methods.
 * @return array
 */
function drs_register_shipping_method( $methods ) {
    $methods['drs_shipping'] = 'DRS_Shipping_Method';
    return $methods;
}
add_filter( 'woocommerce_shipping_methods', 'drs_register_shipping_method' );

/**
 * Ensure global helper hooks are registered early.
 */
require_once DRS_PLUGIN_DIR . 'src/Shipping/Zones.php';

drs_bootstrap_zone_hooks();
